<?php

namespace Tests\Feature;

/**
 * Persistencia de la DDJJ IVA Simple (§H): presentar (POST) guarda el snapshot del
 * período, y el siguiente período toma los arrastres (saldo técnico a favor y libre
 * disponibilidad anteriores) de la DDJJ presentada, sin pasarlos por query.
 */
class DdjjSimplePersistenciaTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'RI')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'DDJJ SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    private function crearPeriodo(int $empresaId, array $auth, string $nombre, string $ini, string $fin): int
    {
        return (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => $nombre, 'fecha_ini' => $ini, 'fecha_fin' => $fin,
        ], $auth)['json']['data']['id'];
    }

    private function cargarVenta(string $base, array $auth, string $neto, string $fecha): void
    {
        $this->postJson("{$base}/ventas", [
            'fecha' => $fecha, 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [['neto_gravado' => $neto, 'iva_alicuota' => '21.000']],
        ], $auth);
    }

    public function test_presentar_persiste_la_ddjj_y_es_idempotente(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();
        $p    = $this->crearPeriodo($e, $auth, '2026-01', '2026-01-01', '2026-01-31');
        $base = "/empresas/{$e}/periodos/{$p}";
        $this->cargarVenta($base, $auth, '1000.00', '2026-01-10'); // débito 210, sin crédito → a pagar 210

        $resp = $this->postJson("{$base}/iva-simple", [], $auth);
        $this->assertSame(200, $resp['status']);
        $this->assertSame('210.00', $resp['json']['data']['posicion_mensual']['saldo_a_pagar']);

        // Re-presentar no duplica: una sola DDJJ por período (upsert).
        $this->postJson("{$base}/iva-simple", [], $auth);
        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM iva_ddjj_simple WHERE empresa_id = {$e} AND periodo_id = {$p}"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_arrastre_automatico_del_periodo_anterior(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        // Período 1: compra con más crédito que débito → saldo técnico a favor del contribuyente.
        $p1   = $this->crearPeriodo($e, $auth, '2026-01', '2026-01-01', '2026-01-31');
        $base1 = "/empresas/{$e}/periodos/{$p1}";
        $this->cargarVenta($base1, $auth, '1000.00', '2026-01-10'); // débito 210
        $this->postJson("{$base1}/compras", [
            'fecha' => '2026-01-15', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [
                ['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000', 'cf_computable' => '420.00'],
            ],
        ], $auth);
        // Presentar P1: 210 − 420 = −210 → saldo técnico a favor del contribuyente 210.
        $r1 = $this->postJson("{$base1}/iva-simple", [], $auth)['json']['data'];
        $this->assertSame('210.00', $r1['determinacion_impuesto']['saldo_tecnico_a_favor_contribuyente']);

        // Período 2 (posterior): solo venta → débito 420. SIN arrastres por query.
        $p2   = $this->crearPeriodo($e, $auth, '2026-02', '2026-02-01', '2026-02-28');
        $base2 = "/empresas/{$e}/periodos/{$p2}";
        $this->cargarVenta($base2, $auth, '2000.00', '2026-02-10'); // débito 420

        $r2 = $this->getJson("{$base2}/iva-simple", $auth);
        $this->assertSame(200, $r2['status']);
        $d = $r2['json']['data'];

        // El saldo técnico anterior (210) se toma solo de la DDJJ del P1:
        // 420 − 0 − 210 = 210 a favor de ARCA → a pagar 210.
        $this->assertSame('210.00', $d['determinacion_impuesto']['saldo_tecnico_anterior']);
        $this->assertSame('210.00', $d['determinacion_impuesto']['saldo_tecnico_a_favor_arca']);
        $this->assertSame('210.00', $d['posicion_mensual']['saldo_a_pagar']);
    }
}
