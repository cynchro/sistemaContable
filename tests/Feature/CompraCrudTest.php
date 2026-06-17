<?php

namespace Tests\Feature;

/**
 * Compras del módulo IVA: agregado (cabecera + discriminación + retenciones) con
 * cálculo de IVA/total por el motor, cf_computable, reglas de período y aislamiento.
 */
class CompraCrudTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(?array $user = null): array
    {
        $auth      = $this->bearer(($user ?? $this->actingAsUser())['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Compras SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    private function compraValida(array $overrides = []): array
    {
        return array_merge([
            'fecha'            => '2026-01-15',
            'neto_no_grav'     => '100.00',
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000',
                 'retenciones' => [['porcentaje' => '3.000', 'importe' => '30.00']]],
                ['neto_gravado' => '500.00', 'iva_alicuota' => '10.500'],
            ],
        ], $overrides);
    }

    private function crearCompra(array $ctx, array $overrides = []): int
    {
        $resp = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/compras",
            $this->compraValida($overrides),
            $ctx['auth'],
        );

        return (int) $resp['json']['data']['id'];
    }

    public function test_crea_compra_y_calcula_total_iva_y_cf_computable(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/compras", $this->compraValida(), $auth);

        $this->assertSame(201, $resp['status']);
        $c = $resp['json']['data'];
        $this->assertSame('1862.50', $c['total']);          // 100 + 1500 + 262.50
        $this->assertCount(2, $c['discriminaciones']);
        $this->assertSame('210.00', $c['discriminaciones'][0]['iva_importe']);
        // cf_computable por defecto = IVA calculado de la línea.
        $this->assertSame('210.00', $c['discriminaciones'][0]['cf_computable']);
        $this->assertCount(1, $c['discriminaciones'][0]['retenciones']);
    }

    public function test_cf_computable_explicito_se_respeta(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/compras", $this->compraValida([
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'cf_computable' => '105.00'],
            ],
        ]), $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame('210.00', $resp['json']['data']['discriminaciones'][0]['iva_importe']);
        $this->assertSame('105.00', $resp['json']['data']['discriminaciones'][0]['cf_computable']);
    }

    public function test_update_recalcula(): void
    {
        $ctx = $this->escenario();
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $ctx;
        $id = $this->crearCompra($ctx);

        $resp = $this->putJson("/empresas/{$e}/periodos/{$p}/compras/{$id}", $this->compraValida([
            'neto_no_grav'     => '0.00',
            'discriminaciones' => [['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000']],
        ]), $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('2420.00', $resp['json']['data']['total']);
        $this->assertCount(1, $resp['json']['data']['discriminaciones']);
    }

    public function test_borra_compra(): void
    {
        $ctx = $this->escenario();
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $ctx;
        $id = $this->crearCompra($ctx);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/periodos/{$p}/compras/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/compras/{$id}", $auth)['status']);
    }

    public function test_no_carga_en_periodo_cerrado(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $this->postJson("/empresas/{$e}/periodos/{$p}/cerrar", [], $auth);

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/compras", $this->compraValida(), $auth);
        $this->assertSame(409, $resp['status']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson("/empresas/{$e}/periodos/{$p}/compras", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }

    public function test_duplicado_por_proveedor_pero_no_entre_proveedores(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $url = "/empresas/{$e}/periodos/{$p}/compras";
        $base = $this->compraValida(['letra' => 'A', 'punto_venta' => '1', 'numero' => '100', 'cuit' => '30111111118']);
        $this->assertSame(201, $this->postJson($url, $base, $auth)['status']);

        // Mismo proveedor (CUIT) + mismo comprobante → duplicado (409), con ceros a la izquierda.
        $repetida = $this->compraValida([
            'letra' => 'A', 'punto_venta' => '0001', 'numero' => '00000100', 'cuit' => '30111111118',
        ]);
        $this->assertSame(409, $this->postJson($url, $repetida, $auth)['status']);

        // Otro proveedor con el mismo punto de venta/número → permitido (201).
        $otroProv = $this->compraValida([
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '100', 'cuit' => '30222222223',
        ]);
        $this->assertSame(201, $this->postJson($url, $otroProv, $auth)['status']);
    }
}
