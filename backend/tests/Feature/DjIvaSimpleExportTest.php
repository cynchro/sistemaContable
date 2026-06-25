<?php

namespace Tests\Feature;

/**
 * DJ IVA Simple (apertura de otros conceptos): descarga de los 4 CSV de ARCA con la
 * operatoria del período distribuida por actividad. Verifica el contenido (débito,
 * crédito, restitución desde NC), el formato y que falte la actividad dé 422.
 */
class DjIvaSimpleExportTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(bool $conActividad = true): array
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (3, 'NC', 'Nota Credito A', -1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");
        $this->pdo->exec("INSERT INTO tipos_documento (id, nombre, cod_afip) VALUES (1, 'CUIT', 80)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'DJ Simple SA'], $auth)['json']['data']['id'];
        if ($conActividad) {
            $this->pdo->exec("UPDATE empresas SET actividad1_id = 620100 WHERE id = {$empresaId}");
        }
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    private function cargar(array $ctx): void
    {
        // Venta gravada (RI) + exento.
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME SA', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '10',
            'exento' => '500.00',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);

        // Nota de crédito de venta (RI) → restitución de débito.
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-20', 'tipo_comprobante_id' => 3, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME SA', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '11',
            'discriminaciones' => [['neto_gravado' => '100.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);

        // Compra gravada → crédito fiscal.
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/compras", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'proveedor_nombre' => 'Proveedor SA', 'letra' => 'A', 'punto_venta' => '5', 'numero' => '23',
            'discriminaciones' => [['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);
    }

    /** @return array{status: int, headers: array<string,string>, raw: string} */
    private function descargar(array $ctx, string $archivo): array
    {
        return $this->getJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/dj-iva-simple/{$archivo}",
            $ctx['auth'],
        );
    }

    public function test_debito_fiscal_gravado_y_exento(): void
    {
        $ctx = $this->escenario();
        $this->cargar($ctx);

        $resp = $this->descargar($ctx, 'debito-fiscal');

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('text/csv', $resp['headers']['Content-Type']);
        $this->assertStringContainsString('DJ_IVA_SIMPLE_DEBITO_FISCAL', $resp['headers']['Content-Disposition']);
        $this->assertSame(
            "620100;1;1;5;1000;210;0;\r\n" .
            "620100;3;;;;;;500\r\n",
            $resp['raw'],
        );
    }

    public function test_restitucion_debito_desde_nota_de_credito(): void
    {
        $ctx = $this->escenario();
        $this->cargar($ctx);

        $resp = $this->descargar($ctx, 'restitucion-debito');

        $this->assertSame("620100;1;1;5;100;21;\r\n", $resp['raw']);
    }

    public function test_credito_fiscal_de_compras(): void
    {
        $ctx = $this->escenario();
        $this->cargar($ctx);

        $resp = $this->descargar($ctx, 'credito-fiscal');

        $this->assertSame("1;5;2000;420;420\r\n", $resp['raw']);
    }

    public function test_sin_actividad_principal_da_422(): void
    {
        $ctx = $this->escenario(conActividad: false);
        $this->cargar($ctx);

        $resp = $this->descargar($ctx, 'debito-fiscal');

        $this->assertSame(422, $resp['status']);
    }
}
