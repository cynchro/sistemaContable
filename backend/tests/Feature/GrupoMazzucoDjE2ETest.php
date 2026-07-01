<?php

namespace Tests\Feature;

/**
 * End-to-end con datos REALES del contador: GRUPO MAZZUCO ARQUITECTOS ASOCIADOS SRL,
 * constructora, período mayo 2026 (carpeta preguntas01-08-2026/). Valida la DJ IVA
 * Simple por actividad combinando DOS estrategias con precedencia receptor → alícuota:
 *   - SANATORIO JUNÍN (30714341398) y DROGUERÍA MITRE (30668100615) → 681098 (alquiler)
 *   - resto (MINISTERIO DE HACIENDA) → construcción por alícuota: 21% → 410021 (no residencial)
 * Contraste: DISTRIBUCION IVA.xlsx (la distribución manual del contador).
 */
class GrupoMazzucoDjE2ETest extends FeatureTestCase
{
    public function test_dj_iva_simple_grupo_mazzuco_mayo_2026(): void
    {
        // Catálogos mínimos (migrate:fresh no siembra): comprobantes con signo + condiciones.
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES
            (1, 'FA', 'Factura A', 1), (6, 'FB', 'Factura B', 1), (8, 'NC', 'Nota de Crédito B', -1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES
            (1, 'RI', 'Responsable Inscripto'), (4, 'EX', 'IVA Exento')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', [
            'nombre' => 'GRUPO MAZZUCO ARQUITECTOS ASOCIADOS SRL', 'cuit' => '30714694541',
        ], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];

        // Actividades NAES de la empresa.
        $act = [];
        foreach (
            ['681098' => 'Servicios inmobiliarios (alquiler)',
                  '410021' => 'Construcción NO residencial',
                  '410011' => 'Construcción residencial'] as $cod => $desc
        ) {
            $act[$cod] = (int) $this->postJson("/empresas/{$empresaId}/actividades", [
                'codigo' => $cod, 'descripcion' => $desc,
            ], $auth)['json']['data']['id'];
        }

        // Estrategia por alícuota (construcción): 21% → no residencial, 10,5% → residencial.
        $this->postJson(
            "/empresas/{$empresaId}/actividades-alicuota",
            ['alicuota' => '21', 'actividad_id' => $act['410021']],
            $auth
        );
        $this->postJson(
            "/empresas/{$empresaId}/actividades-alicuota",
            ['alicuota' => '10.5', 'actividad_id' => $act['410011']],
            $auth
        );

        // Clientes de alquiler + regla por receptor (gana sobre la alícuota).
        $sanatorio = (int) $this->postJson(
            "/empresas/{$empresaId}/clientes",
            ['nombre' => 'SANATORIO JUNIN SA', 'cuit' => '30714341398', 'condicion_iva_id' => 1],
            $auth
        )['json']['data']['id'];
        $drogueria = (int) $this->postJson(
            "/empresas/{$empresaId}/clientes",
            ['nombre' => 'DROGUERIA MITRE SRL', 'cuit' => '30668100615', 'condicion_iva_id' => 1],
            $auth
        )['json']['data']['id'];
        $this->postJson(
            "/empresas/{$empresaId}/actividades-receptor",
            ['cliente_id' => $sanatorio, 'actividad_id' => $act['681098']],
            $auth
        );
        $this->postJson(
            "/empresas/{$empresaId}/actividades-receptor",
            ['cliente_id' => $drogueria, 'actividad_id' => $act['681098']],
            $auth
        );

        // Los 6 comprobantes de venta de mayo 2026 (netos exactos de DISTRIBUCION IVA.xlsx).
        $ventas = [
            ['tipo' => 1, 'letra' => 'A', 'nro' => '434', 'cli' => $sanatorio, 'cond' => 1, 'neto' => '9764966.67'],
            ['tipo' => 1, 'letra' => 'A', 'nro' => '433', 'cli' => $drogueria, 'cond' => 1, 'neto' => '2789990.48'],
            ['tipo' => 6, 'letra' => 'B', 'nro' => '135', 'cli' => null, 'cond' => 4, 'neto' => '22064846.86'],
            ['tipo' => 8, 'letra' => 'B', 'nro' => '21',  'cli' => null, 'cond' => 4, 'neto' => '5630459.62'],
            ['tipo' => 6, 'letra' => 'B', 'nro' => '134', 'cli' => null, 'cond' => 4, 'neto' => '5630459.62'],
            ['tipo' => 6, 'letra' => 'B', 'nro' => '136', 'cli' => null, 'cond' => 4, 'neto' => '5502212.95'],
        ];
        foreach ($ventas as $v) {
            $payload = [
                'fecha' => '2026-05-04', 'tipo_comprobante_id' => $v['tipo'], 'condicion_iva_id' => $v['cond'],
                'letra' => $v['letra'], 'punto_venta' => '2', 'numero' => $v['nro'],
                'discriminaciones' => [['neto_gravado' => $v['neto'], 'iva_alicuota' => '21.000']],
            ];
            if ($v['cli'] !== null) {
                $payload['cliente_id'] = $v['cli'];
            } else {
                $payload['cliente_nombre'] = 'MINISTERIO DE HACIENDA';
            }
            $r = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", $payload, $auth);
            $this->assertSame(201, $r['status'], 'venta ' . $v['nro'] . ': ' . json_encode($r['json']));
        }

        $base    = "/empresas/{$empresaId}/periodos/{$periodoId}/dj-iva-simple";
        $debito  = $this->getJson("{$base}/debito-fiscal", $auth)['raw'];
        $restit  = $this->getJson("{$base}/restitucion-debito", $auth)['raw'];

        fwrite(STDERR, "\n===== DJ IVA SIMPLE — DÉBITO FISCAL (GRUPO MAZZUCO, mayo 2026) =====\n{$debito}");
        fwrite(STDERR, "===== DJ IVA SIMPLE — RESTITUCIÓN DE DÉBITO =====\n{$restit}\n");

        // Débito fiscal (match EXACTO con la distribución del contador):
        //  681098 alquiler (RI=1, alíc 21=cod 5): neto 12.554.957,15 · IVA 2.636.541,00
        //  410021 construcción (Exento→sujeto 3, cod 5): neto 33.197.519,43 · IVA 6.971.479,08
        $this->assertStringContainsString('681098;1;1;5;12554957,15;2636541;0;', $debito);
        $this->assertStringContainsString('410021;1;3;5;33197519,43;6971479,08;0;', $debito);

        // Restitución (NC nº21, construcción): neto 5.630.459,62 · IVA 1.182.396,52
        $this->assertStringContainsString('410021;1;3;5;5630459,62;1182396,52;', $restit);

        // El neto NETO de construcción (débito − restitución) = lo que el contador distribuye a mano.
        // 33.197.519,43 − 5.630.459,62 = 27.567.059,81  ✓ (coincide con DISTRIBUCION IVA.xlsx)
    }
}
