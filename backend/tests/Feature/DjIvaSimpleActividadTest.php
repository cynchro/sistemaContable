<?php

namespace Tests\Feature;

/**
 * DJ IVA Simple — apertura por actividad (v2, A15). Verifica que la actividad de cada
 * venta se resuelva por el mapa de puntos de venta y por el override del comprobante,
 * que los bienes de uso vayan en tipo de operación 2, y que el concepto de la compra
 * salga en el archivo de crédito fiscal.
 */
class DjIvaSimpleActividadTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int, actSuper: int, actConstr: int} */
    private function escenario(): array
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Multi SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $actSuper = (int) $this->postJson("/empresas/{$empresaId}/actividades", [
            'codigo' => '471120', 'descripcion' => 'Supermercado',
        ], $auth)['json']['data']['id'];
        $actConstr = (int) $this->postJson("/empresas/{$empresaId}/actividades", [
            'codigo' => '410021', 'descripcion' => 'Construccion no residencial',
        ], $auth)['json']['data']['id'];

        // Mapa: PV 1 → supermercado, PV 2 → construcción.
        $this->postJson("/empresas/{$empresaId}/actividades-punto-venta", [
            'punto_venta' => '1', 'actividad_id' => $actSuper,
        ], $auth);
        $this->postJson("/empresas/{$empresaId}/actividades-punto-venta", [
            'punto_venta' => '2', 'actividad_id' => $actConstr,
        ], $auth);

        return compact('auth', 'empresaId', 'periodoId') + ['actSuper' => $actSuper, 'actConstr' => $actConstr];
    }

    private function venta(array $ctx, array $extra): void
    {
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", array_merge([
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME', 'letra' => 'A',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $extra), $ctx['auth']);
    }

    public function test_debito_se_distribuye_por_actividad_via_punto_de_venta_y_override(): void
    {
        $ctx = $this->escenario();
        $this->venta($ctx, ['punto_venta' => '1', 'numero' => '1']);  // PV1 → 471120
        $this->venta($ctx, ['punto_venta' => '2', 'numero' => '2']);  // PV2 → 410021
        // override del comprobante (PV1 pero actividad construcción) → 410021:
        $this->venta($ctx, ['punto_venta' => '1', 'numero' => '3', 'actividad_id' => $ctx['actConstr']]);
        // Bien de uso (PV1 → 471120, tipo op 2):
        $this->venta($ctx, ['punto_venta' => '1', 'numero' => '4', 'es_bien_uso' => 'S']);

        $base = "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/dj-iva-simple";
        $resp = $this->getJson("{$base}/debito-fiscal", $ctx['auth']);
        $this->assertSame(200, $resp['status']);
        $raw = $resp['raw'];

        // Supermercado (471120): venta común PV1 (1000) → tipo op 1.
        $this->assertStringContainsString("471120;1;1;5;1000;210;0;", $raw);
        // Construcción (410021): PV2 (1000) + override PV1 (1000) = 2000 al sujeto RI 21%.
        $this->assertStringContainsString("410021;1;1;5;2000;420;0;", $raw);
        // Bien de uso en 471120 → tipo op 2.
        $this->assertStringContainsString("471120;2;1;5;1000;210;0;", $raw);
    }

    public function test_credito_fiscal_usa_el_concepto_de_la_compra(): void
    {
        $ctx = $this->escenario();
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/compras", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'proveedor_nombre' => 'EDESA', 'letra' => 'A', 'punto_venta' => '5', 'numero' => '23',
            'concepto_dj' => 3, // servicios (luz)
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '27.000']],
        ], $ctx['auth']);

        $base = "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/dj-iva-simple";
        $resp = $this->getJson("{$base}/credito-fiscal", $ctx['auth']);
        $this->assertSame(200, $resp['status']);
        // Concepto 3 (servicios), alícuota 27% → código 6.
        $this->assertStringContainsString('3;6;1000;270;270', $resp['raw']);
    }
}
