<?php

namespace Tests\Feature;

/**
 * Ventas del módulo IVA: agregado (cabecera + discriminación + percepciones) con
 * cálculo de IVA/total por el motor, reglas de período (abierto, fecha en rango)
 * y aislamiento por tenant. Las percepciones integran el total (respuestas.md A1).
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
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000'],
                ['neto_gravado' => '500.00', 'iva_alicuota' => '10.500'],
            ],
        ], $overrides);
    }

    /** Crea un tipo de percepción propio del estudio y devuelve su id. */
    private function crearTipoPercepcion(array $auth, array $data = []): int
    {
        $resp = $this->postJson('/tipos-retencion', array_merge([
            'nombre'       => 'Perc. IIBB',
            'tipo_rg3685'  => 3,
            'alicuota'     => '2.5',
            'base_calculo' => 'neto_gravado',
        ], $data), $auth);

        return (int) $resp['json']['data']['id'];
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

    public function test_percepcion_integra_el_total_y_calcula_importe(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        // IIBB Catamarca 2,5% sobre el neto total (1500) = 37.50.
        $tipoId = $this->crearTipoPercepcion($auth);

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $this->ventaValida([
            'percepciones' => [['tipo_retencion_id' => $tipoId]],
        ]), $auth);

        $this->assertSame(201, $resp['status']);
        $v = $resp['json']['data'];
        $this->assertCount(1, $v['percepciones']);
        $this->assertSame('1500.00', $v['percepciones'][0]['base']);
        $this->assertSame('37.50', $v['percepciones'][0]['importe']);
        $this->assertSame('1900.00', $v['total']); // 1862.50 + 37.50
    }

    public function test_percepcion_iva_por_tramos(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        // Percepción IVA: 3% sobre el neto al 21% (1000) + 1,5% sobre el neto al 10,5% (500).
        $tipoId = $this->crearTipoPercepcion($auth, [
            'nombre' => 'Perc. IVA', 'tipo_rg3685' => 1, 'base_calculo' => 'iva_percepcion', 'alicuota' => '0',
        ]);

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $this->ventaValida([
            'percepciones' => [['tipo_retencion_id' => $tipoId]],
        ]), $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame('37.50', $resp['json']['data']['percepciones'][0]['importe']); // 30 + 7.50
    }

    public function test_percepcion_sin_tipo_falla(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", $this->ventaValida([
            'percepciones' => [['alicuota' => '2.5']],
        ]), $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('percepciones', $resp['json']['errors']);
    }

    public function test_mover_comprobante_a_otro_periodo(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p1] = $this->escenario();
        // Período destino cuyo rango incluye la fecha de la venta (2026-01-15).
        $p2 = (int) $this->postJson("/empresas/{$e}/periodos", [
            'nombre' => '2026-Q1', 'fecha_ini' => '2026-01-10', 'fecha_fin' => '2026-02-28',
        ], $auth)['json']['data']['id'];
        $id = $this->crearVenta(['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p1]);

        $resp = $this->postJson(
            "/empresas/{$e}/periodos/{$p1}/ventas/{$id}/mover",
            ['periodo_destino_id' => $p2],
            $auth,
        );
        $this->assertSame(200, $resp['status']);

        // Quedó en el destino y ya no está en el origen.
        $this->assertSame(200, $this->getJson("/empresas/{$e}/periodos/{$p2}/ventas/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p1}/ventas/{$id}", $auth)['status']);
    }

    public function test_mover_con_fecha_fuera_del_destino_falla(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p1] = $this->escenario();
        $p2 = (int) $this->postJson("/empresas/{$e}/periodos", [
            'nombre' => '2026-03', 'fecha_ini' => '2026-03-01', 'fecha_fin' => '2026-03-31',
        ], $auth)['json']['data']['id'];
        $id = $this->crearVenta(['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p1]);

        $resp = $this->postJson(
            "/empresas/{$e}/periodos/{$p1}/ventas/{$id}/mover",
            ['periodo_destino_id' => $p2],
            $auth,
        );
        $this->assertSame(422, $resp['status']);
    }

    public function test_fk_inexistente_da_422(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->postJson(
            "/empresas/{$e}/periodos/{$p}/ventas",
            $this->ventaValida(['tipo_comprobante_id' => 999]),
            $auth,
        );

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('tipo_comprobante_id', $resp['json']['errors']);
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
