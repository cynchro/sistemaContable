<?php

namespace Tests\Feature;

/**
 * Exportación del subdiario a CSV/TXT: descarga (body crudo) con encabezados de
 * archivo, formato configurable y aislamiento por tenant.
 */
class ExportarSubdiarioTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Export SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_exporta_ventas_csv_descargable(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME', 'letra' => 'A', 'punto_venta' => '00001', 'numero' => '00000001',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("/empresas/{$e}/periodos/{$p}/exportar/ventas", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('text/csv', $resp['headers']['Content-Type']);
        $disposition = $resp['headers']['Content-Disposition'];
        $this->assertStringContainsString('attachment; filename="libro-ventas-', $disposition);

        $lineas = explode("\r\n", trim($resp['raw']));
        $this->assertStringContainsString('Fecha,Comprobante,Tipo', $lineas[0]);
        $this->assertStringContainsString('A0000100000001', $lineas[1]);
        $this->assertStringContainsString('1000.00', $lineas[1]);
        $this->assertStringContainsString('210.00', $lineas[1]);
    }

    public function test_formato_txt_usa_punto_y_coma(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();

        $resp = $this->getJson("/empresas/{$e}/periodos/{$p}/exportar/compras?formato=txt", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('text/plain', $resp['headers']['Content-Type']);
        $this->assertStringContainsString('.txt', $resp['headers']['Content-Disposition']);
        $this->assertStringContainsString('Fecha;Comprobante;Tipo', $resp['raw']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/periodos/{$p}/exportar/ventas", $bobAuth)['status']);
    }
}
