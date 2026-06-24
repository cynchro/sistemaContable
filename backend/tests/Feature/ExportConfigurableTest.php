<?php

namespace Tests\Feature;

/**
 * Exportador TXT configurable (§D): el tenant define un formato (campos/orden/separador)
 * y exporta el subdiario del período con él. Valida los campos contra la lista blanca.
 */
class ExportConfigurableTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'RI')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Export Cfg SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    private function crearFormato(array $auth): int
    {
        return (int) $this->postJson('/iva/export-formatos', [
            'nombre' => 'Sistema X', 'tipo' => 'delimitado', 'separador' => '|', 'eol' => 'lf',
            'campos' => [
                ['campo' => 'fecha', 'formato_fecha' => 'd/m/Y'],
                ['campo' => 'letra'],
                ['campo' => 'total', 'decimales' => 2],
            ],
        ], $auth)['json']['data']['id'];
    }

    public function test_crear_listar_y_exportar(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'periodoId' => $p] = $this->escenario();
        $formatoId = $this->crearFormato($auth);

        // El formato quedó persistido con sus campos.
        $lista = $this->getJson('/iva/export-formatos', $auth);
        $this->assertSame(200, $lista['status']);
        $this->assertCount(1, $lista['json']['data']);

        $ver = $this->getJson("/iva/export-formatos/{$formatoId}", $auth);
        $this->assertCount(3, $ver['json']['data']['campos']);

        // Venta: neto 1000 @ 21 → total 1210, letra A, fecha 2026-01-10.
        $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1, 'letra' => 'A',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $resp = $this->getJson("/empresas/{$e}/periodos/{$p}/exportar-config/{$formatoId}?tipo=ventas", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('text/plain', $resp['headers']['Content-Type']);
        $this->assertStringContainsString('Sistema X_ventas.txt', $resp['headers']['Content-Disposition']);
        $this->assertSame("10/01/2026|A|1210.00\n", $resp['raw']);
    }

    public function test_campo_fuera_de_la_lista_blanca_da_422(): void
    {
        ['auth' => $auth] = $this->escenario();

        $resp = $this->postJson('/iva/export-formatos', [
            'nombre' => 'Malo', 'tipo' => 'delimitado',
            'campos' => [['campo' => 'columna_inexistente']],
        ], $auth);

        $this->assertSame(422, $resp['status']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['auth' => $auth] = $this->escenario();
        $formatoId = $this->crearFormato($auth);

        $bob = $this->bearer($this->actingAsUser()['token']);
        $this->assertSame(404, $this->getJson("/iva/export-formatos/{$formatoId}", $bob)['status']);
    }
}
