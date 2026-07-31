<?php

namespace Tests\Feature;

/**
 * Clientes del módulo IVA: ahora son sujetos del Padrón Único activados con rol
 * 'cliente' para la empresa (ver PadronUnicoSujetosTest para la reutilización entre
 * empresas del mismo tenant). El CUIT es obligatorio y debe tener dígito verificador
 * válido — es la clave del padrón.
 */
class IvaClienteCrudTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int} [auth, empresaId] */
    private function empresaDe(array $user): array
    {
        $auth    = $this->bearer($user['token']);
        $empresa = $this->postJson('/empresas', ['nombre' => 'ACME SA'], $auth);

        return [$auth, (int) $empresa['json']['data']['id']];
    }

    public function test_crud_completo(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $created = $this->postJson("/empresas/{$empresaId}/clientes", [
            'nombre'    => 'Juan Pérez',
            'cuit'      => '20111111112',
            'localidad' => 'Córdoba',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('Juan Pérez', $created['json']['data']['nombre']);

        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/clientes", $auth)['json']['data']);

        $updated = $this->putJson("/empresas/{$empresaId}/clientes/{$id}", [
            'nombre'    => 'Juan C. Pérez',
            'cuit'      => '20111111112',
            'localidad' => 'Rosario',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Rosario', $updated['json']['data']['localidad']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/clientes/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$empresaId}/clientes/{$id}", $auth)['status']);
    }

    public function test_busqueda_por_nombre_o_cuit_y_orden(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $this->postJson("/empresas/{$empresaId}/clientes", ['nombre' => 'Zeta SA', 'cuit' => '30111111118'], $auth);
        $this->postJson("/empresas/{$empresaId}/clientes", ['nombre' => 'Alfa SRL', 'cuit' => '30710968973'], $auth);

        // Búsqueda por nombre.
        $r = $this->getJson("/empresas/{$empresaId}/clientes?q=alfa", $auth);
        $this->assertCount(1, $r['json']['data']);
        $this->assertSame('Alfa SRL', $r['json']['data'][0]['nombre']);

        // Búsqueda por CUIT (parcial).
        $r = $this->getJson("/empresas/{$empresaId}/clientes?q=3011", $auth);
        $this->assertCount(1, $r['json']['data']);
        $this->assertSame('Zeta SA', $r['json']['data'][0]['nombre']);

        // Orden por nombre (default): Alfa antes que Zeta.
        $r = $this->getJson("/empresas/{$empresaId}/clientes", $auth);
        $this->assertSame('Alfa SRL', $r['json']['data'][0]['nombre']);

        // Orden por CUIT: el 3011… antes que el 3071…
        $r = $this->getJson("/empresas/{$empresaId}/clientes?orden=cuit", $auth);
        $this->assertSame('30111111118', $r['json']['data'][0]['cuit']);
    }

    public function test_crear_sin_nombre_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/clientes", ['cuit' => '20111111112'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombre', $resp['json']['errors']);
    }

    public function test_crear_sin_cuit_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/clientes", ['nombre' => 'Juan'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuit', $resp['json']['errors']);
    }

    public function test_cuit_con_digito_verificador_invalido_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        // Mismo formato, dígito verificador incorrecto (el válido termina en 2, no 9).
        $resp = $this->postJson(
            "/empresas/{$empresaId}/clientes",
            ['nombre' => 'Juan', 'cuit' => '20111111119'],
            $auth,
        );

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuit', $resp['json']['errors']);
    }

    public function test_no_se_acceden_clientes_de_empresa_de_otro_tenant(): void
    {
        [, $aliceEmpresaId] = $this->empresaDe($this->actingAsUser());
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson("/empresas/{$aliceEmpresaId}/clientes", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }

    public function test_fk_inexistente_da_422(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/clientes", [
            'nombre'           => 'Juan',
            'cuit'             => '20111111112',
            'condicion_iva_id' => 999, // no existe
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('condicion_iva_id', $resp['json']['errors']);
    }

    public function test_referencias_validas_crean_ok(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        // Catálogo global vacío en test → sembramos una condición de IVA.
        $this->pdo->exec(
            "INSERT INTO condiciones_iva (id, codigo, nombre, codigo_afip) VALUES (1, 'RI', 'Resp. Insc.', '01')"
        );

        $resp = $this->postJson("/empresas/{$empresaId}/clientes", [
            'nombre'           => 'Juan',
            'cuit'             => '20111111112',
            'condicion_iva_id' => 1,
        ], $auth);

        $this->assertSame(201, $resp['status']);
    }
}
