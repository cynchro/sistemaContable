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
        $sujetos = $resp['json']['data']['results'];
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
        $this->assertCount(1, $resp['json']['data']['results']);
        $this->assertSame('Muchay SRL', $resp['json']['data']['results'][0]['nombre']);
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
        $this->assertCount(0, $resp['json']['data']['results']);
    }

    /**
     * Informe del cliente 10/08/2026, pedido 5a: "mezclar el padrón de proveedores y el de
     * clientes en una sola integración no es posible... hacelos separados".
     */
    public function test_rol_separa_el_padron_en_proveedores_y_clientes(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaA  = (int) $this->postJson('/empresas', ['nombre' => 'Empresa D'], $auth)['json']['data']['id'];
        $empresaB  = (int) $this->postJson('/empresas', ['nombre' => 'Empresa E'], $auth)['json']['data']['id'];

        // Mismo CUIT: proveedor en A, cliente en B.
        $this->postJson("/empresas/{$empresaA}/proveedores", [
            'nombre' => 'Doble Rol SRL', 'cuit' => '30777777773',
        ], $auth);
        $this->postJson("/empresas/{$empresaB}/clientes", [
            'nombre' => 'Doble Rol SRL', 'cuit' => '30777777773',
        ], $auth);
        // Solo proveedor, en ninguna empresa como cliente.
        $this->postJson("/empresas/{$empresaA}/proveedores", [
            'nombre' => 'Solo Proveedor SRL', 'cuit' => '30888888884',
        ], $auth);

        $proveedores = $this->getJson('/padron-unico?rol=proveedor', $auth)['json']['data']['results'];
        $this->assertCount(2, $proveedores);
        $doble = current(array_filter($proveedores, fn ($s) => $s['cuit'] === '30777777773'));
        // La vista de proveedores no debe traer la activación de cliente de este mismo sujeto.
        $this->assertCount(1, $doble['empresas']);
        $this->assertSame('proveedor', $doble['empresas'][0]['rol']);

        $clientes = $this->getJson('/padron-unico?rol=cliente', $auth)['json']['data']['results'];
        $this->assertCount(1, $clientes);
        $this->assertSame('30777777773', $clientes[0]['cuit']);
        $this->assertSame('cliente', $clientes[0]['empresas'][0]['rol']);
    }

    public function test_rol_invalido_devuelve_422(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $resp = $this->getJson('/padron-unico?rol=lo_que_sea', $auth);
        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('rol', $resp['json']['errors']);
    }
}
