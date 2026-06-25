<?php

namespace Tests\Feature;

/**
 * Reporte secundario de percepciones del período: agrupadas por tipo (ventas y
 * compras) con totales de importe.
 */
class ReportePercepcionesTest extends FeatureTestCase
{
    public function test_agrupa_percepciones_de_ventas_y_compras_con_totales(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Reporte SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $tipoPerc = (int) $this->postJson('/tipos-retencion', [
            'nombre' => 'Perc. IIBB', 'tipo_rg3685' => 3, 'alicuota' => '2.5', 'base_calculo' => 'neto_gravado',
        ], $auth)['json']['data']['id'];

        // Dos ventas con la misma percepción (2,5% sobre 1000 = 25 cada una → 50 en total).
        foreach (['10', '11'] as $nro) {
            $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
                'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
                'cliente_nombre' => 'ACME', 'letra' => 'A', 'punto_venta' => '1', 'numero' => $nro,
                'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
                'percepciones'     => [['tipo_retencion_id' => $tipoPerc]],
            ], $auth);
        }

        // Una compra con percepción (2,5% sobre 2000 = 50).
        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'proveedor_nombre' => 'Prov', 'letra' => 'A', 'punto_venta' => '5', 'numero' => '23',
            'discriminaciones' => [['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000']],
            'percepciones'     => [['tipo_retencion_id' => $tipoPerc]],
        ], $auth);

        $resp = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/reportes/percepciones", $auth);

        $this->assertSame(200, $resp['status']);
        $data = $resp['json']['data'];

        $this->assertCount(1, $data['ventas']);
        $this->assertSame('Perc. IIBB', $data['ventas'][0]['tipo_nombre']);
        $this->assertSame('2', (string) $data['ventas'][0]['cantidad']);
        $this->assertSame('50.00', $data['totales']['ventas']);
        $this->assertSame('50.00', $data['totales']['compras']);
    }

    public function test_periodo_sin_percepciones_da_listas_vacias(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Vacia SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-02', 'fecha_ini' => '2026-02-01', 'fecha_fin' => '2026-02-28',
        ], $auth)['json']['data']['id'];

        $resp = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/reportes/percepciones", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame([], $resp['json']['data']['ventas']);
        $this->assertSame('0.00', $resp['json']['data']['totales']['compras']);
    }
}
