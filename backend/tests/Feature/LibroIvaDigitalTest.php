<?php

namespace Tests\Feature;

/**
 * Libro IVA Digital (Portal IVA): descarga de los 4 archivos de ancho fijo de ARCA.
 * Verifica los largos exactos del diseño de registro (266/62/325/84), que la
 * percepción caiga en su columna y el aislamiento por tenant.
 */
class LibroIvaDigitalTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int, tipoPerc: int} */
    private function escenario(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)"
        );
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");
        $this->pdo->exec("INSERT INTO tipos_documento (id, nombre, cod_afip) VALUES (1, 'CUIT', 80)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Digital SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];
        $tipoPerc = (int) $this->postJson('/tipos-retencion', [
            'nombre' => 'Perc. IIBB', 'tipo_rg3685' => 3, 'alicuota' => '2.5', 'base_calculo' => 'neto_gravado',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId, 'tipoPerc' => $tipoPerc];
    }

    private function crearVenta(array $ctx): void
    {
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'tipo_documento_id' => 1, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME SA', 'cuit' => '30711217920',
            'letra' => 'A', 'punto_venta' => '2', 'numero' => '40',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
            'percepciones'     => [['tipo_retencion_id' => $ctx['tipoPerc']]],
        ], $ctx['auth']);
    }

    private function crearCompra(array $ctx): void
    {
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/compras", [
            'fecha' => '2026-01-12', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'proveedor_nombre' => 'Proveedor SA', 'cuit' => '30710968973',
            'letra' => 'A', 'punto_venta' => '5', 'numero' => '23453',
            'discriminaciones' => [['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);
    }

    /** @return list<string> Líneas (sin CRLF final) del archivo descargado. */
    private function descargar(array $ctx, string $archivo): array
    {
        $resp = $this->getJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/libro-iva-digital/{$archivo}",
            $ctx['auth'],
        );
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('text/plain', $resp['headers']['Content-Type']);
        $this->assertStringContainsString('LIBRO_IVA_DIGITAL', $resp['headers']['Content-Disposition']);

        return explode("\r\n", rtrim($resp['raw'], "\r\n"));
    }

    public function test_ventas_cbte_descarga_con_largo_266_y_percepcion(): void
    {
        $ctx = $this->escenario();
        $this->crearVenta($ctx);

        $lineas = $this->descargar($ctx, 'ventas-cbte');

        $this->assertCount(1, $lineas);
        $this->assertSame(266, strlen($lineas[0]));
        $this->assertSame('001', substr($lineas[0], 8, 3));               // FA + A → CbteTipo 1
        $this->assertSame('000000000123500', substr($lineas[0], 108, 15)); // total 1000 + 210 + 25 = 1235.00
        $this->assertSame('000000000002500', substr($lineas[0], 183, 15)); // perc. IIBB 2,5% de 1000 = 25.00
    }

    public function test_ventas_alicuotas_descarga_con_largo_62(): void
    {
        $ctx = $this->escenario();
        $this->crearVenta($ctx);

        $lineas = $this->descargar($ctx, 'ventas-alicuotas');

        $this->assertCount(1, $lineas);
        $this->assertSame(62, strlen($lineas[0]));
        $this->assertSame('000000000100000', substr($lineas[0], 28, 15)); // neto 1000.00
        $this->assertSame('0005', substr($lineas[0], 43, 4));             // 21% → Id 5
    }

    public function test_compras_archivos_con_largos_325_y_84(): void
    {
        $ctx = $this->escenario();
        $this->crearCompra($ctx);

        $cbte = $this->descargar($ctx, 'compras-cbte');
        $this->assertCount(1, $cbte);
        $this->assertSame(325, strlen($cbte[0]));
        $this->assertSame('000000000042000', substr($cbte[0], 239, 15)); // cf computable 2000×21% = 420.00

        $alic = $this->descargar($ctx, 'compras-alicuotas');
        $this->assertCount(1, $alic);
        $this->assertSame(84, strlen($alic[0]));
    }

    public function test_ventas_anulados_descarga_con_largo_44(): void
    {
        $ctx = $this->escenario();
        // Un comprobante normal (no debe aparecer en anulados) + uno anulado.
        $this->crearVenta($ctx);
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-11', 'tipo_comprobante_id' => 9, 'tipo_documento_id' => 1, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'Anulada SA', 'cuit' => '30711217920',
            'letra' => 'A', 'punto_venta' => '2', 'numero' => '41',
            'anulado' => 'S', 'fecha_anulacion' => '2026-01-15',
            'discriminaciones' => [['neto_gravado' => '500.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);

        $lineas = $this->descargar($ctx, 'ventas-anulados');

        $this->assertCount(1, $lineas);                          // solo el anulado
        $this->assertSame(44, strlen($lineas[0]));
        $this->assertSame('20260111', substr($lineas[0], 0, 8));  // fecha del comprobante
        $this->assertSame('001', substr($lineas[0], 8, 3));       // FA + A → CbteTipo 1
        $this->assertSame('00002', substr($lineas[0], 11, 5));    // punto de venta
        $this->assertSame('00000000000000000041', substr($lineas[0], 16, 20)); // número
        $this->assertSame('20260115', substr($lineas[0], 36, 8)); // fecha de anulación
    }

    public function test_archivo_desconocido_da_422(): void
    {
        $ctx = $this->escenario();

        $resp = $this->getJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/libro-iva-digital/inexistente",
            $ctx['auth'],
        );
        $this->assertSame(422, $resp['status']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        $ctx = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/libro-iva-digital/ventas-cbte",
            $bobAuth,
        );
        $this->assertSame(404, $resp['status']);
    }
}
