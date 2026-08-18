<?php

namespace Tests\Feature;

/**
 * "CUIT único" (informe del cliente 10/08/2026, pedido 3): GET /padron-unico/cuit/{cuit} — antes
 * de dar de alta una empresa (contribuyente), el frontend consulta si ese CUIT ya está en el
 * padrón de sujetos (cliente/proveedor de alguna empresa del estudio), para ofrecer reusar esos
 * datos en vez de tipearlos de nuevo.
 */
class PadronUnicoPorCuitTest extends FeatureTestCase
{
    public function test_encuentra_sujeto_por_cuit(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa D'], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30222222229', 'domicilio' => 'Ruta 9 km 4',
        ], $auth);

        $resp = $this->getJson('/padron-unico/cuit/30222222229', $auth);

        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertTrue($d['encontrado']);
        $this->assertSame('Muchay SRL', $d['nombre']);
        $this->assertSame('Ruta 9 km 4', $d['domicilio']);
    }

    public function test_cuit_no_existe_devuelve_encontrado_false_sin_error(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/padron-unico/cuit/20999999999', $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertFalse($resp['json']['data']['encontrado']);
        $this->assertSame('20999999999', $resp['json']['data']['cuit']);
    }

    public function test_no_mezcla_sujetos_de_otro_tenant(): void
    {
        $alice     = $this->actingAsUser();
        $bob       = $this->actingAsUser();
        $aliceAuth = $this->bearer($alice['token']);
        $bobAuth   = $this->bearer($bob['token']);

        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Alice'], $aliceAuth)
            ['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor de Alice', 'cuit' => '30666666662',
        ], $aliceAuth);

        $resp = $this->getJson('/padron-unico/cuit/30666666662', $bobAuth);

        $this->assertSame(200, $resp['status']);
        $this->assertFalse($resp['json']['data']['encontrado']);
    }

    public function test_requiere_autenticacion(): void
    {
        $resp = $this->getJson('/padron-unico/cuit/30222222229');

        $this->assertSame(401, $resp['status']);
    }
}
