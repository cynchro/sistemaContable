<?php

namespace Tests\Feature;

/**
 * Padrón Único de Sujetos IVA (pedido del contador, ver
 * documentacion/pedido-padron-unico-contribuyentes.md): un mismo CUIT es una sola fila
 * por tenant, reutilizada por todas sus empresas — ya no se duplica un cliente/
 * proveedor por cada empresa que opera con él.
 */
class PadronUnicoSujetosTest extends FeatureTestCase
{
    public function test_mismo_cuit_en_dos_empresas_del_mismo_tenant_reutiliza_el_sujeto(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaA = (int) $this->postJson('/empresas', ['nombre' => 'Empresa A'], $auth)['json']['data']['id'];
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];

        $creadoA = $this->postJson("/empresas/{$empresaA}/proveedores", [
            'nombre' => 'Proveedor Combustible', 'cuit' => '30111111118',
        ], $auth);
        $this->assertSame(201, $creadoA['status']);
        $idA = (int) $creadoA['json']['data']['id'];

        // La empresa B da de alta el MISMO CUIT: no crea una fila nueva, reutiliza el
        // sujeto del padrón (mismo id) y ambas empresas lo tienen activado.
        $creadoB = $this->postJson("/empresas/{$empresaB}/proveedores", [
            'nombre' => 'Proveedor Combustible SRL', 'cuit' => '30-11111111-8',
        ], $auth);
        $this->assertSame(201, $creadoB['status']);
        $this->assertSame($idA, (int) $creadoB['json']['data']['id']);

        $this->assertCount(1, $this->getJson("/empresas/{$empresaA}/proveedores", $auth)['json']['data']);
        $this->assertCount(1, $this->getJson("/empresas/{$empresaB}/proveedores", $auth)['json']['data']);

        // El alta desde B actualizó el nombre del maestro: A también lo ve actualizado
        // (es la misma fila — "todo una sola cosa").
        $vistoDesdeA = $this->getJson("/empresas/{$empresaA}/proveedores/{$idA}", $auth);
        $this->assertSame('Proveedor Combustible SRL', $vistoDesdeA['json']['data']['nombre']);
    }

    public function test_proveedor_de_otra_empresa_del_mismo_tenant_es_utilizable_directo(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaA = (int) $this->postJson('/empresas', ['nombre' => 'Empresa A'], $auth)['json']['data']['id'];
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $periodoA = (int) $this->postJson("/empresas/{$empresaA}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        // El proveedor se cargó únicamente en la empresa B.
        $provId = (int) $this->postJson("/empresas/{$empresaB}/proveedores", [
            'nombre' => 'Prov B', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        // La empresa A lo usa directo en una compra: ya no hace falta darlo de alta ahí
        // primero (antes daba 422 "de otra empresa"; ahora es el mismo padrón del tenant).
        $resp = $this->postJson("/empresas/{$empresaA}/periodos/{$periodoA}/compras", [
            'fecha' => '2026-01-15', 'proveedor_id' => $provId,
            'neto_no_grav' => '100.00', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
        ], $auth);

        $this->assertSame(201, $resp['status']);

        // Y queda activado (aparece) en el listado de proveedores de la empresa A también.
        $this->assertCount(1, $this->getJson("/empresas/{$empresaA}/proveedores", $auth)['json']['data']);
    }

    public function test_cuit_de_otro_tenant_da_422(): void
    {
        $authAlice = $this->bearer($this->actingAsUser()['token']);
        $empresaAlice = (int) $this->postJson('/empresas', ['nombre' => 'Alice SA'], $authAlice)['json']['data']['id'];

        $authBob = $this->bearer($this->actingAsUser()['token']);
        $empresaBob = (int) $this->postJson('/empresas', ['nombre' => 'Bob SA'], $authBob)['json']['data']['id'];
        $provBob = (int) $this->postJson("/empresas/{$empresaBob}/proveedores", [
            'nombre' => 'Prov de Bob', 'cuit' => '30111111118',
        ], $authBob)['json']['data']['id'];

        $periodoAlice = (int) $this->postJson("/empresas/{$empresaAlice}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $authAlice)['json']['data']['id'];

        // Alice no puede usar el proveedor de Bob (otro tenant): el id ni existe en su padrón.
        $resp = $this->postJson("/empresas/{$empresaAlice}/periodos/{$periodoAlice}/compras", [
            'fecha' => '2026-01-15', 'proveedor_id' => $provBob,
            'neto_no_grav' => '100.00', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
        ], $authAlice);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('proveedor_id', $resp['json']['errors']);
    }
}
