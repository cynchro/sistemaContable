<?php

namespace Tests\Feature;

/**
 * Libro IVA detallado: subtotales por (condición IVA, alícuota) de ventas y
 * compras, ponderados por signo (las NC restan dentro de su alícuota).
 */
class LibroIvaDetalleTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES "
            . "(9, 'FA', 'Factura', 1), (3, 'NC', 'Nota de Credito', -1)"
        );
        $this->pdo->exec(
            "INSERT INTO condiciones_iva (id, codigo, nombre) VALUES "
            . "(1, 'RI', 'Responsable Inscripto'), (2, 'MO', 'Monotributo')"
        );

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Detalle SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_detalle_agrupa_por_alicuota_y_nc_resta(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        // Venta factura cond 1: 1000 @ 21 y 500 @ 10.5.
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000'],
                ['neto_gravado' => '500.00', 'iva_alicuota' => '10.500'],
            ],
        ], $auth);

        // NC venta cond 1: 200 @ 21 (resta de la alícuota 21).
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 3, 'condicion_iva_id' => 1,
            'discriminaciones' => [['neto_gravado' => '200.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        // Compra factura cond 2: 600 @ 21.
        $this->postJson("{$base}/compras", [
            'fecha' => '2026-01-15', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 2,
            'discriminaciones' => [['neto_gravado' => '600.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("{$base}/libro-iva", $auth);
        $this->assertSame(200, $resp['status']);

        $ventas = $this->indexar($resp['json']['data']['ventas']);
        $this->assertSame('800.00', $ventas['1|21.000']['neto_gravado']); // 1000 - 200
        $this->assertSame('168.00', $ventas['1|21.000']['iva']);          // 210 - 42
        $this->assertSame('500.00', $ventas['1|10.500']['neto_gravado']);
        $this->assertSame('52.50', $ventas['1|10.500']['iva']);

        $compras = $this->indexar($resp['json']['data']['compras']);
        $this->assertSame('600.00', $compras['2|21.000']['neto_gravado']);
        $this->assertSame('126.00', $compras['2|21.000']['iva']);
        $this->assertSame('126.00', $compras['2|21.000']['cf_computable']);
    }

    public function test_periodo_de_otro_tenant_da_404(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/libro-iva", $bobAuth)['status']);
    }

    /**
     * @param  list<array<string,mixed>> $rows
     * @return array<string, array<string,mixed>> indexado por "condicion|alicuota"
     */
    private function indexar(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['condicion_iva_id'] . '|' . $row['alicuota']] = $row;
        }

        return $out;
    }
}
