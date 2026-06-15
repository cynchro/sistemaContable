<?php

namespace Tests\Feature;

/**
 * DDJJ de IVA del período (F2002): débito fiscal (ventas), crédito fiscal
 * computable (compras, usa cf_computable) y saldo técnico.
 */
class DeclaracionIvaTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'RI')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'DDJJ SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_ddjj_debito_credito_computable_y_saldo(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        // Venta: neto 1000 @ 21 (débito 210), más 100 exento.
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'exento' => '100.00',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        // Compra: IVA 210 pero solo 150 computable.
        $this->postJson("{$base}/compras", [
            'fecha' => '2026-01-15', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'cf_computable' => '150.00'],
            ],
        ], $auth);

        $resp = $this->getJson("{$base}/ddjj", $auth);
        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];

        $this->assertSame('210.00', $d['debito_fiscal']['iva']);
        $this->assertSame('210.00', $d['credito_fiscal']['iva']);
        $this->assertSame('150.00', $d['credito_fiscal']['computable']);
        $this->assertSame('100.00', $d['conceptos_ventas']['exento']);
        // Saldo técnico = débito 210 − crédito computable 150 = 60 (no 0).
        $this->assertSame('60.00', $d['saldo']['saldo_tecnico']);
        $this->assertCount(1, $d['debito_fiscal']['por_alicuota']);
    }

    public function test_periodo_de_otro_tenant_da_404(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/ddjj", $bobAuth)['status']);
    }
}
