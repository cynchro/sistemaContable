<?php

namespace Tests\Feature;

/**
 * Reporte analítico del Mayor por rango de fechas (R2): movimientos por línea (neto)
 * agrupados en cascada con subtotales y filtrables por cuenta/provincia/proveedor/rango.
 */
class ReporteMayorTest extends FeatureTestCase
{
    public function test_reporte_por_rango_agrupa_en_cascada_con_subtotales(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Reporte SA'], $auth)['json']['data']['id'];
        // Dos períodos distintos (el reporte cruza períodos por rango de fechas).
        $abr = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-04', 'fecha_ini' => '2026-04-01', 'fecha_fin' => '2026-04-30',
        ], $auth)['json']['data']['id'];
        $may = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];

        $comb = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustible',
        ], $auth)['json']['data']['id'];

        // Combustible de YPF: 1000 en abril + 2000 en mayo.
        $this->postJson("/empresas/{$empresaId}/periodos/{$abr}/compras", [
            'fecha' => '2026-04-10', 'tipo_comprobante_id' => 9, 'proveedor_nombre' => 'YPF', 'cuit' => '30-1-3',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'cuenta_id' => $comb]],
        ], $auth);
        $this->postJson("/empresas/{$empresaId}/periodos/{$may}/compras", [
            'fecha' => '2026-05-10', 'tipo_comprobante_id' => 9, 'proveedor_nombre' => 'YPF', 'cuit' => '30-1-3',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '2',
            'discriminaciones' => [['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000', 'cuenta_id' => $comb]],
        ], $auth);

        // Rango que abarca los dos meses, agrupando cuenta → proveedor.
        $r = $this->getJson(
            "/empresas/{$empresaId}/reportes/mayor?desde=2026-04-01&hasta=2026-05-31&agrupar=cuenta,proveedor",
            $auth,
        );
        $this->assertSame(200, $r['status']);
        $data = $r['json']['data'];
        $this->assertSame('3000.00', $data['totales']['neto']);
        $this->assertCount(1, $data['grupos']);
        $grupoCuenta = $data['grupos'][0];
        $this->assertSame('cuenta', $grupoCuenta['dimension']);
        $this->assertSame('3000.00', $grupoCuenta['subtotal']['neto']);
        // Un proveedor (YPF) con el subtotal, y 2 comprobantes hoja.
        $this->assertCount(1, $grupoCuenta['hijos']);
        $this->assertSame('3000.00', $grupoCuenta['hijos'][0]['subtotal']['neto']);
        $this->assertCount(2, $grupoCuenta['hijos'][0]['hijos']);
    }

    public function test_filtro_por_rango_excluye_fuera_de_fecha(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Filtro SA'], $auth)['json']['data']['id'];
        $per = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];
        $cta = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Gastos',
        ], $auth)['json']['data']['id'];

        $this->postJson("/empresas/{$empresaId}/periodos/{$per}/compras", [
            'fecha' => '2026-05-20', 'tipo_comprobante_id' => 9, 'proveedor_nombre' => 'X',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'cuenta_id' => $cta]],
        ], $auth);

        // Rango anterior al comprobante → sin movimientos.
        $r = $this->getJson("/empresas/{$empresaId}/reportes/mayor?desde=2026-05-01&hasta=2026-05-15", $auth);
        $this->assertSame('0.00', $r['json']['data']['totales']['neto']);
        $this->assertSame(0, $r['json']['data']['totales']['cantidad']);
    }

    public function test_sin_rango_de_fechas_devuelve_422(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Sin Rango SA'], $auth)['json']['data']['id'];

        $r = $this->getJson("/empresas/{$empresaId}/reportes/mayor", $auth);
        $this->assertSame(422, $r['status']);
        $this->assertArrayHasKey('desde', $r['json']['errors']);
    }

    public function test_rango_con_demasiados_movimientos_devuelve_422(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Volumen SA'], $auth)['json']['data']['id'];
        $per = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];

        // Una compra con más líneas de discriminación que MAX_MOVIMIENTOS (20.000) — inserción
        // masiva directa (no vía API) para no hacer 20.000 requests HTTP en un test.
        $this->pdo->exec("INSERT INTO compras (periodo_id, fecha) VALUES ({$per}, '2026-05-10')");
        $compraId = (int) $this->pdo->lastInsertId();

        $filas = [];
        for ($i = 0; $i < 20001; $i++) {
            $filas[] = "({$compraId})";
        }
        // MySQL soporta multi-row INSERT; se divide en lotes para no exceder max_allowed_packet.
        foreach (array_chunk($filas, 5000) as $lote) {
            $this->pdo->exec('INSERT INTO compra_discriminaciones (compra_id) VALUES ' . implode(',', $lote));
        }

        $r = $this->getJson("/empresas/{$empresaId}/reportes/mayor?desde=2026-05-01&hasta=2026-05-31", $auth);
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('acotá las fechas', $r['json']['errors']['desde'][0]);
    }
}
