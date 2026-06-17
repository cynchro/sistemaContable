<?php

namespace Tests\Feature;

/**
 * Ventas del módulo IVA: agregado (cabecera + discriminación + retenciones) con
 * cálculo de IVA/total por el motor, reglas de período (abierto, fecha en rango)
 * y aislamiento por tenant.
 */
class VentaCrudTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(?array $user = null): array
    {
        $auth    = $this->bearer(($user ?? $this->actingAsUser())['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Ventas SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    /** Crea una venta válida y devuelve su id. */
    private function crearVenta(array $ctx, array $overrides = []): int
    {
        $resp = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas",
            $this->ventaValida($overrides),
            $ctx['auth'],
        );

        return (int) $resp['json']['data']['id'];
    }

    private function ventaValida(array $overrides = []): array
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

    public function test_crea_venta_y_calcula_total_e_iva(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $this->ventaValida(), $auth);

        $this->assertSame(201, $resp['status']);
        $v = $resp['json']['data'];
        $this->assertSame('1862.50', $v['total']);        // 100 + 1500 + 262.50
        $this->assertCount(2, $v['discriminaciones']);
        $this->assertSame('210.00', $v['discriminaciones'][0]['iva_importe']);
        $this->assertSame('52.50', $v['discriminaciones'][1]['iva_importe']);
        $this->assertCount(1, $v['discriminaciones'][0]['retenciones']);
        $this->assertSame('30.00', $v['discriminaciones'][0]['retenciones'][0]['importe']);
    }

    public function test_update_recalcula_y_reemplaza_lineas(): void
    {
        $ctx = $this->escenario();
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $ctx;
        $id = $this->crearVenta($ctx);

        $resp = $this->putJson("/empresas/{$e}/periodos/{$p}/ventas/{$id}", $this->ventaValida([
            'neto_no_grav'     => '0.00',
            'discriminaciones' => [['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000']],
        ]), $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('2420.00', $resp['json']['data']['total']); // 2000 + 420
        $this->assertCount(1, $resp['json']['data']['discriminaciones']);
    }

    public function test_borra_venta(): void
    {
        $ctx = $this->escenario();
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $ctx;
        $id = $this->crearVenta($ctx);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/periodos/{$p}/ventas/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/ventas/{$id}", $auth)['status']);
    }

    public function test_no_carga_en_periodo_cerrado(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $this->postJson("/empresas/{$e}/periodos/{$p}/cerrar", [], $auth);

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $this->ventaValida(), $auth);
        $this->assertSame(409, $resp['status']);
    }

    public function test_fecha_fuera_del_periodo_falla(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson(
            "/empresas/{$e}/periodos/{$p}/ventas",
            $this->ventaValida(['fecha' => '2026-02-10']),
            $auth,
        );

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('fecha', $resp['json']['errors']);
    }

    public function test_discriminacion_invalida_falla(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $this->ventaValida([
            'discriminaciones' => [['neto_gravado' => 'no-numero', 'iva_alicuota' => '21']],
        ]), $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('discriminaciones', $resp['json']['errors']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson("/empresas/{$e}/periodos/{$p}/ventas", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }

    public function test_no_permite_comprobante_duplicado(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $primera = $this->ventaValida(['letra' => 'A', 'punto_venta' => '1', 'numero' => '100']);
        $this->assertSame(201, $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $primera, $auth)['status']);

        // Mismo comprobante con ceros a la izquierda en pv/número → se detecta igual.
        $repetida = $this->ventaValida(['letra' => 'A', 'punto_venta' => '0001', 'numero' => '00000100']);
        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $repetida, $auth);
        $this->assertSame(409, $resp['status']);
    }

    public function test_editar_el_mismo_comprobante_no_es_duplicado(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $id = (int) $this->postJson(
            "/empresas/{$e}/periodos/{$p}/ventas",
            $this->ventaValida(['letra' => 'A', 'punto_venta' => '1', 'numero' => '100']),
            $auth,
        )['json']['data']['id'];

        $resp = $this->putJson(
            "/empresas/{$e}/periodos/{$p}/ventas/{$id}",
            $this->ventaValida(['letra' => 'A', 'punto_venta' => '1', 'numero' => '100']),
            $auth,
        );
        $this->assertSame(200, $resp['status']);
    }
}
