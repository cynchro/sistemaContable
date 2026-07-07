<?php

namespace Tests\Feature;

/**
 * Mayor de cuentas (mayorización interna): resumen por cuenta (debe/haber/saldo) y
 * detalle de los comprobantes imputados a una cuenta.
 */
class MayorTest extends FeatureTestCase
{
    public function test_resumen_y_detalle_por_cuenta(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Mayor SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];

        $comb = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustible',
        ], $auth)['json']['data']['id'];
        $prov = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '2001', 'nombre' => 'Proveedores',
        ], $auth)['json']['data']['id'];

        foreach (['100', '200'] as $nro) {
            $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
                'fecha' => '2026-05-10', 'tipo_comprobante_id' => 9, 'proveedor_nombre' => 'YPF',
                'letra' => 'A', 'punto_venta' => '1', 'numero' => $nro,
                'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
                'cuenta_debe_id'   => $comb, 'cuenta_haber_id' => $prov,
            ], $auth);
        }

        $resumen = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/mayor", $auth);
        $this->assertSame(200, $resumen['status']);
        $porCuenta = [];
        foreach ($resumen['json']['data'] as $r) {
            $porCuenta[(int) $r['id']] = $r;
        }
        $this->assertSame('2420.00', $porCuenta[$comb]['debe']);
        $this->assertSame('2420.00', $porCuenta[$comb]['saldo']);
        $this->assertSame('2420.00', $porCuenta[$prov]['haber']);
        $this->assertSame('-2420.00', $porCuenta[$prov]['saldo']);
        $this->assertSame('2', (string) $porCuenta[$comb]['movimientos']);

        $detalle = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/mayor/{$comb}", $auth);
        $this->assertSame(200, $detalle['status']);
        $this->assertCount(2, $detalle['json']['data']);
        $this->assertSame('debe', $detalle['json']['data'][0]['lado']);
        $this->assertSame('1210.00', $detalle['json']['data'][0]['importe']);
    }

    public function test_nota_credito_resta_en_el_mayor(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (3, 'NC', 'NC', -1)");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'NC SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-05', 'fecha_ini' => '2026-05-01', 'fecha_fin' => '2026-05-31',
        ], $auth)['json']['data']['id'];
        $cuenta = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Gastos',
        ], $auth)['json']['data']['id'];

        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-05-10', 'tipo_comprobante_id' => 9, 'proveedor_nombre' => 'X',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
            'cuenta_debe_id'   => $cuenta,
        ], $auth);
        $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-05-11', 'tipo_comprobante_id' => 3, 'proveedor_nombre' => 'X',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '2',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
            'cuenta_debe_id'   => $cuenta,
        ], $auth);

        $resumen = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/mayor", $auth);
        // FA (+1210) + NC (−1210) = 0 en el debe de la cuenta.
        $this->assertSame('0.00', $resumen['json']['data'][0]['debe']);
    }
}
