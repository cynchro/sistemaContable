<?php

namespace Tests\Feature;

/**
 * Clientes del módulo IVA: CRUD anidado bajo empresa, acotado al tenant dueño.
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
            'localidad' => 'Rosario',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Rosario', $updated['json']['data']['localidad']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/clientes/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$empresaId}/clientes/{$id}", $auth)['status']);
    }

    public function test_crear_sin_nombre_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/clientes", ['cuit' => '20111111112'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombre', $resp['json']['errors']);
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
            'condicion_iva_id' => 999, // no existe
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('condicion_iva_id', $resp['json']['errors']);
    }

    public function test_cuenta_de_otra_empresa_da_422(): void
    {
        [$auth, $empresaA] = $this->empresaDe($this->actingAsUser());
        // Otra empresa del mismo tenant, con una cuenta propia.
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Otra SA'], $auth)['json']['data']['id'];
        $cuentaB = (int) $this->postJson("/empresas/{$empresaB}/cuentas", [
            'codigo' => '1.1.01', 'nombre' => 'Caja',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaA}/clientes", [
            'nombre'    => 'Juan',
            'cuenta_id' => $cuentaB, // existe, pero es de otra empresa
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuenta_id', $resp['json']['errors']);
    }

    public function test_referencias_validas_crean_ok(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        // Catálogo global vacío en test → sembramos una condición de IVA.
        $this->pdo->exec(
            "INSERT INTO condiciones_iva (id, codigo, nombre, codigo_afip) VALUES (1, 'RI', 'Resp. Insc.', '01')"
        );
        $cuenta = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '1.1.01', 'nombre' => 'Caja',
        ], $auth)['json']['data']['id'];
        $rubro = (int) $this->postJson('/rubros', ['nombre' => 'Servicios'], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaId}/clientes", [
            'nombre'           => 'Juan',
            'condicion_iva_id' => 1,
            'cuenta_id'        => $cuenta,
            'rubro_id'         => $rubro,
        ], $auth);

        $this->assertSame(201, $resp['status']);
    }
}
