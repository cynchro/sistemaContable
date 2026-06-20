<?php

namespace Tests\Feature;

/**
 * DDJJ "IVA Simple" del período (F.2051 del Portal IVA, reemplaza al F2002):
 * encadena el débito/crédito computable del período con los arrastres (saldo técnico
 * anterior, saldo de libre disponibilidad anterior y retenciones/percepciones/pagos),
 * que llegan por query string.
 */
class IvaSimpleTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'RI')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'IVA Simple SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_iva_simple_con_arrastre_a_favor_del_contribuyente(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        // Venta: neto 1000 @ 21 → débito fiscal 210.
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        // Compra: IVA 420, todo computable → crédito fiscal computable 420.
        $this->postJson("{$base}/compras", [
            'fecha' => '2026-01-15', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [
                ['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000', 'cf_computable' => '420.00'],
            ],
        ], $auth);

        // Arrastres por query: saldo técnico anterior 100, libre disp. anterior 30, ret/perc 20.
        $query = 'saldo_tecnico_anterior=100'
            . '&saldo_libre_disponibilidad_anterior=30'
            . '&retenciones_percepciones_pagos=20';
        $resp = $this->getJson("{$base}/iva-simple?{$query}", $auth);
        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];

        // débito 210 − crédito 420 − saldo anterior 100 = −310 → a favor del contribuyente 310.
        $this->assertSame('210.00', $d['determinacion_impuesto']['debito_fiscal']);
        $this->assertSame('420.00', $d['determinacion_impuesto']['credito_fiscal']);
        $this->assertSame('0.00', $d['determinacion_impuesto']['saldo_tecnico_a_favor_arca']);
        $this->assertSame('310.00', $d['determinacion_impuesto']['saldo_tecnico_a_favor_contribuyente']);
        // ARCA 0 → libre disponibilidad = 30 + 20 = 50.
        $this->assertSame('0.00', $d['posicion_mensual']['saldo_a_pagar']);
        $this->assertSame('50.00', $d['posicion_mensual']['saldo_libre_disponibilidad_periodo']);
    }

    public function test_iva_simple_saldo_a_pagar_sin_arrastres(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $base = "/empresas/{$e}/periodos/{$p}";

        // Solo venta: débito 210, sin crédito ni arrastres → 210 a pagar.
        $this->postJson("{$base}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("{$base}/iva-simple", $auth);
        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];

        $this->assertSame('210.00', $d['determinacion_impuesto']['saldo_tecnico_a_favor_arca']);
        $this->assertSame('210.00', $d['posicion_mensual']['saldo_a_pagar']);
        $this->assertSame('0.00', $d['posicion_mensual']['saldo_libre_disponibilidad_periodo']);
    }

    public function test_periodo_de_otro_tenant_da_404(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/iva-simple", $bobAuth)['status']);
    }
}
