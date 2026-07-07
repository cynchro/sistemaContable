<?php

namespace Tests\Feature;

/**
 * Exportación SIFERE Convenio Multilateral V4: percepciones de IIBB sufridas en compras,
 * por jurisdicción. Valida el TXT de ancho fijo (ver Tests\Unit\...\SifereWriterTest para
 * el byte a byte del formato).
 */
class SifereExportTest extends FeatureTestCase
{
    public function test_exporta_percepciones_iibb_de_la_jurisdiccion(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");
        $this->pdo->exec("INSERT INTO provincias (id, nombre, jurisdiccion) VALUES (17, 'Salta', 917)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Convenio SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];

        $tipoPerc = (int) $this->postJson('/tipos-retencion', [
            'nombre' => 'Perc. IIBB Salta', 'tipo_rg3685' => 3, 'alicuota' => '2.5', 'base_calculo' => 'neto_gravado',
        ], $auth)['json']['data']['id'];

        // Compra con percepción de IIBB de Salta (importe informado = 3679,72).
        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-05-07', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'proveedor_nombre' => 'RURAL SANTA FE SOC', 'cuit' => '30633358202',
            'letra' => 'A', 'punto_venta' => '3', 'numero' => '1441462',
            'discriminaciones' => [['neto_gravado' => '100000.00', 'iva_alicuota' => '21.000']],
            'percepciones'     => [['tipo_retencion_id' => $tipoPerc, 'importe' => '3679.72', 'provincia_id' => 17]],
        ], $auth);

        $resp = $this->getJson(
            "/empresas/{$empresaId}/periodos/{$periodoId}/sifere/percepciones?provincia_id=17",
            $auth,
        );

        $this->assertSame(200, $resp['status']);
        $this->assertSame("91730-63335820-207/05/2026000301441462FA00003679,72\r\n", $resp['raw']);
    }

    public function test_sin_provincia_da_422(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'X SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];

        $resp = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/sifere/percepciones", $auth);

        $this->assertSame(422, $resp['status']);
    }
}
