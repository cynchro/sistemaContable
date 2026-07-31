<?php

namespace Tests\Feature;

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4):
 * lista todos los sujetos del tenant, sin filtrar por empresa, con las empresas donde cada uno
 * está activo. Distinto de PadronController (consulta al padrón de AFIP, no relacionado).
 */
class PadronUnicoGlobalTest extends FeatureTestCase
{
    public function test_lista_sujetos_de_todas_las_empresas_del_tenant_con_sus_activaciones(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaA  = (int) $this->postJson('/empresas', ['nombre' => 'Empresa A'], $auth)['json']['data']['id'];
        $empresaB  = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];

        // Mismo CUIT activado como proveedor en A y como cliente en B: debe aparecer una sola
        // vez en el padrón global, con las dos activaciones.
        $this->postJson("/empresas/{$empresaA}/proveedores", [
            'nombre' => 'Distribuidora SRL', 'cuit' => '30111111118',
        ], $auth);
        $this->postJson("/empresas/{$empresaB}/clientes", [
            'nombre' => 'Distribuidora SRL', 'cuit' => '30111111118',
        ], $auth);

        $resp = $this->getJson('/padron-unico', $auth);
        $this->assertSame(200, $resp['status']);
        $sujetos = $resp['json']['data'];
        $this->assertCount(1, $sujetos);
        $this->assertSame('30111111118', $sujetos[0]['cuit']);
        $this->assertCount(2, $sujetos[0]['empresas']);

        $roles = array_column($sujetos[0]['empresas'], 'rol');
        sort($roles);
        $this->assertSame(['cliente', 'proveedor'], $roles);
    }

    public function test_filtra_por_nombre_o_cuit(): void
    {
        $auth     = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa C'], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30222222229',
        ], $auth);
        $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Otro Proveedor', 'cuit' => '30666666662',
        ], $auth);

        $resp = $this->getJson('/padron-unico?q=Muchay', $auth);
        $this->assertCount(1, $resp['json']['data']);
        $this->assertSame('Muchay SRL', $resp['json']['data'][0]['nombre']);
    }

    public function test_no_mezcla_sujetos_de_otro_tenant(): void
    {
        $alice = $this->actingAsUser();
        $bob   = $this->actingAsUser();
        $aliceAuth = $this->bearer($alice['token']);
        $bobAuth   = $this->bearer($bob['token']);

        $empresaAlice = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Alice'], $aliceAuth)
            ['json']['data']['id'];
        $this->postJson("/empresas/{$empresaAlice}/proveedores", [
            'nombre' => 'Proveedor Alice', 'cuit' => '30444444440',
        ], $aliceAuth);

        $resp = $this->getJson('/padron-unico', $bobAuth);
        $this->assertSame(200, $resp['status']);
        $this->assertCount(0, $resp['json']['data']);
    }
}
