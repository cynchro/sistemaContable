<?php

namespace Tests\Feature;

/**
 * Totales de período (libro IVA): derivados de ventas y compras, con el signo del
 * tipo de comprobante (las notas de crédito restan) y el saldo de IVA.
 *
 * El escenario inserta tipos de comprobante con signo vía PDO (en la base de test
 * los catálogos no están sembrados) para ejercitar la ponderación por signo.
 */
class LibroIvaTotalesTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        // Tipos de comprobante: 9 = factura (+1), 3 = nota de crédito (-1).
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES "
            . "(9, 'FA', 'Factura', 1), (3, 'NC', 'Nota de Credito', -1)"
        );

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Libro SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_totales_con_nota_de_credito_y_saldo(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        // Venta factura: neto 1000 @ 21% → total 1210, IVA 210.
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9,
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        // Nota de crédito de venta: neto 200 @ 21% → total 242, IVA 42 (resta).
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 3,
            'discriminaciones' => [['neto_gravado' => '200.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        // Compra factura: neto 500 @ 21% → total 605, IVA 105.
        $this->postJson("{$base}/compras", [
            'fecha' => '2026-01-15', 'tipo_comprobante_id' => 9,
            'discriminaciones' => [['neto_gravado' => '500.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("{$base}/totales", $auth);

        $this->assertSame(200, $resp['status']);
        $t = $resp['json']['data'];
        $this->assertSame('968.00', $t['total_ventas']);   // 1210 - 242
        $this->assertSame('605.00', $t['total_compras']);
        $this->assertSame('168.00', $t['iva_ventas']);     // 210 - 42
        $this->assertSame('105.00', $t['iva_compras']);
        $this->assertSame('63.00', $t['saldo_iva']);       // 168 - 105 a pagar
    }

    public function test_periodo_sin_comprobantes_da_cero(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->getJson("/empresas/{$e}/periodos/{$p}/totales", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('0.00', $resp['json']['data']['total_ventas']);
        $this->assertSame('0.00', $resp['json']['data']['saldo_iva']);
    }

    public function test_periodo_de_otro_tenant_da_404(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/totales", $bobAuth)['status']);
    }
}
