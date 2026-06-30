<?php

namespace Tests\Feature;

/**
 * DJ IVA Simple — apertura por actividad, Fase 3 (A15): estrategia de PORCENTAJES FIJOS
 * (caso Acevedo). Cuando la empresa tiene coeficientes cargados, el neto del período se
 * reparte entre las actividades por su coeficiente (no se resuelve por comprobante).
 */
class DjIvaSimpleActividadFase3Test extends FeatureTestCase
{
    public function test_reparte_el_periodo_por_coeficientes(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Lubricentro SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $act1 = (int) $this->postJson("/empresas/{$empresaId}/actividades", [
            'codigo' => '466119', 'descripcion' => 'Combustibles',
        ], $auth)['json']['data']['id'];
        $act2 = (int) $this->postJson("/empresas/{$empresaId}/actividades", [
            'codigo' => '453210', 'descripcion' => 'Cubiertas',
        ], $auth)['json']['data']['id'];

        // Coeficientes: 60% combustibles, 40% cubiertas (suman 1).
        $this->postJson("/empresas/{$empresaId}/actividades-coeficiente", [
            'actividad_id' => $act1, 'coeficiente' => '0.6',
        ], $auth);
        $this->postJson("/empresas/{$empresaId}/actividades-coeficiente", [
            'actividad_id' => $act2, 'coeficiente' => '0.4',
        ], $auth);

        // Una sola factura de 1000 @ 21% (IVA 210). Aunque su PV no esté mapeado, en modo
        // porcentajes fijos se reparte: 600/126 y 400/84.
        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'Cliente', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $raw = $this->getJson(
            "/empresas/{$empresaId}/periodos/{$periodoId}/dj-iva-simple/debito-fiscal",
            $auth,
        )['raw'];

        $this->assertStringContainsString("466119;1;1;5;600;126;0;", $raw);  // 60%
        $this->assertStringContainsString("453210;1;1;5;400;84;0;", $raw);   // 40%
    }
}
