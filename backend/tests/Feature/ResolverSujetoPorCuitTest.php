<?php

namespace Tests\Feature;

/**
 * Match CUIT→padrón en el alta/import de compras y ventas (documento "Satélite Visual IVA" §3,
 * ver documentacion/analisis-satelite-visual-iva.md §7.2/§7.3). Hallazgo que motivó esto: el
 * importador manda `proveedor_nombre`/`cliente_nombre` + `cuit` como texto libre, nunca
 * `proveedor_id`/`cliente_id` — sin este resolver, un comprobante importado nunca tocaba
 * `iva_sujetos` aunque el CUIT ya existiera en el padrón. `CompraService`/`VentaService` ahora
 * completan el id automáticamente cuando el CUIT matchea, sin tocar el frontend.
 */
class ResolverSujetoPorCuitTest extends FeatureTestCase
{
    public function test_compra_sin_proveedor_id_pero_con_cuit_matcheado_resuelve_el_proveedor(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Resolver SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        // Simula lo que manda hoy el importador: cuit + proveedor_nombre en texto libre, SIN
        // proveedor_id — igual que ImportarPage.tsx.
        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-15', 'proveedor_nombre' => 'Muchay SRL', 'cuit' => '30111111118',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($provId, (int) $resp['json']['data']['proveedor_id']);

        // Y quedó activado (aparece en el listado de proveedores de la empresa).
        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/proveedores", $auth)['json']['data']);
    }

    public function test_venta_sin_cliente_id_pero_con_cuit_matcheado_resuelve_el_cliente(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Resolver SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $cliId = (int) $this->postJson("/empresas/{$empresaId}/clientes", [
            'nombre' => 'Acceso Sur SRL', 'cuit' => '30888888884',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
            'fecha' => '2026-01-15', 'cliente_nombre' => 'Acceso Sur SRL', 'cuit' => '30888888884',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($cliId, (int) $resp['json']['data']['cliente_id']);
        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/clientes", $auth)['json']['data']);
    }

    public function test_compra_con_cuit_sin_match_sigue_creandose_como_sujeto_ocasional(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Resolver SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        // Ningún proveedor con este CUIT en el padrón: no debe fallar, sigue como "ocasional".
        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-15', 'proveedor_nombre' => 'Proveedor Nuevo', 'cuit' => '30999999995',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertNull($resp['json']['data']['proveedor_id']);
        $this->assertSame('Proveedor Nuevo', $resp['json']['data']['proveedor_nombre']);
    }

    public function test_proveedor_id_explicito_no_se_pisa_aunque_el_cuit_sea_de_otro_sujeto(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Resolver SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $provA = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor A', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor B', 'cuit' => '30888888884',
        ], $auth);

        // proveedor_id explícito (A) + cuit de otro (B): el explícito gana, no se resuelve por CUIT.
        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-15', 'proveedor_id' => $provA, 'cuit' => '30888888884',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($provA, (int) $resp['json']['data']['proveedor_id']);
    }
}
