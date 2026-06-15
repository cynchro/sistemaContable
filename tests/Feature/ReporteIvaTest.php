<?php

namespace Tests\Feature;

/**
 * Reportes subdiario / libro IVA: listado de comprobantes enriquecido (con nombres
 * de catálogos e importes calculados) y totales al pie.
 */
class ReporteIvaTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Reporte SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_subdiario_ventas_lista_comprobantes_con_lookups_y_totales(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME', 'letra' => 'A', 'punto_venta' => '00001', 'numero' => '00000001',
            'exento' => '50.00',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("{$base}/reportes/ventas", $auth);
        $this->assertSame(200, $resp['status']);

        $row = $resp['json']['data']['comprobantes'][0];
        $this->assertSame('Factura A', $row['tipo_comprobante_nombre']);
        $this->assertSame('Responsable Inscripto', $row['condicion_nombre']);
        $this->assertSame('A0000100000001', $row['comprobante']);
        $this->assertSame('1000.00', $row['neto_gravado']);
        $this->assertSame('210.00', $row['iva']);

        $tot = $resp['json']['data']['totales'];
        $this->assertSame('1000.00', $tot['neto_gravado']);
        $this->assertSame('210.00', $tot['iva']);
        $this->assertSame('50.00', $tot['exento']);
        $this->assertSame('1260.00', $tot['total']); // 1000 + 210 + 50
    }

    public function test_subdiario_compras_incluye_cf_computable(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        $this->postJson("{$base}/compras", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'proveedor_nombre' => 'Proveedor X',
            'discriminaciones' => [
                ['neto_gravado' => '500.00', 'iva_alicuota' => '21.000', 'cf_computable' => '80.00'],
            ],
        ], $auth);

        $resp = $this->getJson("{$base}/reportes/compras", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('105.00', $resp['json']['data']['comprobantes'][0]['iva']);
        $this->assertSame('80.00', $resp['json']['data']['totales']['cf_computable']);
    }

    public function test_periodo_de_otro_tenant_da_404(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/reportes/ventas", $bobAuth)['status']);
    }
}
