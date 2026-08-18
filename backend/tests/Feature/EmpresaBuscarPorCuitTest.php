<?php

namespace Tests\Feature;

/**
 * "CUIT único" (informe del cliente 10/08/2026, pedido 3): GET /empresas/cuit/{cuit} — antes de
 * dar de alta un sujeto (cliente/proveedor), el frontend consulta si ese CUIT ya es una empresa
 * propia del estudio, para ofrecer reusar esos datos en vez de tipearlos de nuevo.
 */
class EmpresaBuscarPorCuitTest extends FeatureTestCase
{
    public function test_encuentra_empresa_propia_por_cuit(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $this->postJson('/empresas', [
            'nombre' => 'Grupo AC SRL', 'cuit' => '30111111118', 'domicilio' => 'Av. Siempre Viva 123',
        ], $auth);

        $resp = $this->getJson('/empresas/cuit/30111111118', $auth);

        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertTrue($d['encontrado']);
        $this->assertSame('Grupo AC SRL', $d['nombre']);
        $this->assertSame('Av. Siempre Viva 123', $d['domicilio']);
    }

    public function test_cuit_no_existe_devuelve_encontrado_false_sin_error(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/empresas/cuit/20999999999', $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertFalse($resp['json']['data']['encontrado']);
        $this->assertSame('20999999999', $resp['json']['data']['cuit']);
    }

    public function test_no_mezcla_empresas_de_otro_tenant(): void
    {
        $alice     = $this->actingAsUser();
        $bob       = $this->actingAsUser();
        $aliceAuth = $this->bearer($alice['token']);
        $bobAuth   = $this->bearer($bob['token']);

        $this->postJson('/empresas', ['nombre' => 'Empresa de Alice', 'cuit' => '30222222229'], $aliceAuth);

        $resp = $this->getJson('/empresas/cuit/30222222229', $bobAuth);

        $this->assertSame(200, $resp['status']);
        $this->assertFalse($resp['json']['data']['encontrado']);
    }

    public function test_requiere_autenticacion(): void
    {
        $resp = $this->getJson('/empresas/cuit/30111111118');

        $this->assertSame(401, $resp['status']);
    }
}
