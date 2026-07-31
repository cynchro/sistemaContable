<?php

namespace Tests\Feature;

/**
 * Bandeja de pendientes de solo lectura (documento "Satélite Visual IVA" §3, ver
 * documentacion/analisis-satelite-visual-iva.md §7.7 paso 3, variante "liviana" elegida por el
 * usuario): lista, por período, los comprobantes que quedaron sin proveedor/cliente del Padrón
 * Único ("sujeto ocasional"). No bloquea nada — solo lectura, se resuelven con el PUT existente
 * (que ya matchea por CUIT desde la Parte 2, ver ResolverSujetoPorCuitTest).
 */
class PendientesComprobantesTest extends FeatureTestCase
{
    public function test_compras_pendientes_lista_solo_las_sin_proveedor_identificado(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Pendientes SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        // Con match (proveedor del padrón): NO debe aparecer en pendientes.
        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-10', 'proveedor_id' => $provId,
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        // Sin match (CUIT que no está en el padrón): SÍ debe aparecer.
        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-12', 'proveedor_nombre' => 'Proveedor Nuevo', 'cuit' => '30999999995',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '2',
            'discriminaciones' => [['neto_gravado' => '500.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras/pendientes", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertCount(1, $resp['json']['data']);
        $this->assertSame('Proveedor Nuevo', $resp['json']['data'][0]['proveedor_nombre']);
        $this->assertSame('30999999995', $resp['json']['data'][0]['cuit']);
    }

    public function test_ventas_pendientes_lista_solo_las_sin_cliente_identificado(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Pendientes SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $cliId = (int) $this->postJson("/empresas/{$empresaId}/clientes", [
            'nombre' => 'Acceso Sur SRL', 'cuit' => '30888888884',
        ], $auth)['json']['data']['id'];

        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
            'fecha' => '2026-01-10', 'cliente_id' => $cliId,
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
            'fecha' => '2026-01-12', 'cliente_nombre' => 'Consumidor Final Sin CUIT',
            'letra' => 'B', 'punto_venta' => '1', 'numero' => '2',
            'discriminaciones' => [['neto_gravado' => '500.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas/pendientes", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertCount(1, $resp['json']['data']);
        $this->assertSame('Consumidor Final Sin CUIT', $resp['json']['data'][0]['cliente_nombre']);
    }

    public function test_resolver_asignando_proveedor_id_por_put_lo_saca_de_pendientes(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Pendientes SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor Tardío', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        // Se cargó ANTES de que existiera el proveedor en el padrón: queda pendiente.
        $compraId = (int) $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-10', 'proveedor_nombre' => 'Proveedor Tardío', 'cuit' => '30222222229',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth)['json']['data']['id'];

        $this->assertCount(
            1,
            $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras/pendientes", $auth)['json']['data'],
        );

        // Se resuelve con el PUT existente, asignando el proveedor_id real.
        $this->putJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras/{$compraId}", [
            'fecha' => '2026-01-10', 'proveedor_id' => $provId,
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertCount(
            0,
            $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras/pendientes", $auth)['json']['data'],
        );
    }
}
