<?php

namespace Tests\Feature;

use App\Modules\Iva\Repositories\SujetoEmpresaRepository;
use App\Modules\Iva\Repositories\ImputacionContableRepository;

/**
 * Imputación contable del Padrón Único (documento "Satélite Visual IVA" §5, ver
 * documentacion/analisis-satelite-visual-iva.md §7.1): cuenta por defecto de un proveedor en
 * una empresa + excepción por punto de venta (caso MUCHAY SRL). Todavía sin endpoints HTTP
 * propios (paso posterior, ver §7.7) — se ejercitan los repositorios directamente sobre datos
 * creados por la API real (empresa/cuentas/proveedor).
 */
class ImputacionContableTest extends FeatureTestCase
{
    public function test_sin_regla_cargada_no_resuelve_ninguna_cuenta(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $imputacion = new ImputacionContableRepository($this->pdo);

        $this->assertNull($imputacion->resolverCuenta($empresaId, $provId, null));
        $this->assertNull($imputacion->resolverCuenta($empresaId, $provId, '0003'));
    }

    public function test_cuenta_por_defecto_del_sujeto_en_la_empresa(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Catamarca Combustibles', 'cuit' => '30222222229',
        ], $auth)['json']['data']['id'];

        $sujetoEmpresas = new SujetoEmpresaRepository($this->pdo);
        $sujetoEmpresas->setCuenta($empresaId, $provId, 'proveedor', $cuentaId);

        $imputacion = new ImputacionContableRepository($this->pdo);

        // Sin punto de venta y con cualquier punto de venta sin regla propia: cae al default.
        $this->assertSame($cuentaId, $imputacion->resolverCuenta($empresaId, $provId, null));
        $this->assertSame($cuentaId, $imputacion->resolverCuenta($empresaId, $provId, '0001'));
    }

    public function test_regla_por_punto_de_venta_tiene_precedencia_sobre_el_default(): void
    {
        // Caso MUCHAY SRL (documento §5.4): un mismo proveedor factura combustible desde el
        // PV 0003 pero otro insumo desde el PV 0004 — cada PV pisa el default del proveedor.
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $default = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5000', 'nombre' => 'Gastos Generales',
        ], $auth)['json']['data']['id'];
        $combustible = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30666666662',
        ], $auth)['json']['data']['id'];

        $sujetoEmpresas = new SujetoEmpresaRepository($this->pdo);
        $sujetoEmpresas->setCuenta($empresaId, $provId, 'proveedor', $default);

        $imputacion = new ImputacionContableRepository($this->pdo);
        $imputacion->setPuntoVenta($empresaId, $provId, '0003', $combustible);

        // PV 0003: regla específica gana sobre el default.
        $this->assertSame($combustible, $imputacion->resolverCuenta($empresaId, $provId, '0003'));
        // Otro PV (0004) sin regla propia: cae al default del proveedor.
        $this->assertSame($default, $imputacion->resolverCuenta($empresaId, $provId, '0004'));
        // Sin punto de venta informado: también cae al default.
        $this->assertSame($default, $imputacion->resolverCuenta($empresaId, $provId, null));

        $reglas = $imputacion->puntosVenta($empresaId, $provId);
        $this->assertCount(1, $reglas);
        $this->assertSame('0003', $reglas[0]['punto_venta']);
        $this->assertSame($combustible, (int) $reglas[0]['cuenta_id']);

        // Borrada la regla del PV, vuelve a caer al default.
        $imputacion->deletePuntoVenta((int) $reglas[0]['id'], $empresaId);
        $this->assertSame($default, $imputacion->resolverCuenta($empresaId, $provId, '0003'));
    }

    public function test_reasignar_punto_de_venta_actualiza_la_cuenta(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $cuentaA = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Cuenta A',
        ], $auth)['json']['data']['id'];
        $cuentaB = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5002', 'nombre' => 'Cuenta B',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor X', 'cuit' => '30444444440',
        ], $auth)['json']['data']['id'];

        $imputacion = new ImputacionContableRepository($this->pdo);
        $imputacion->setPuntoVenta($empresaId, $provId, '0001', $cuentaA);
        $imputacion->setPuntoVenta($empresaId, $provId, '0001', $cuentaB);

        $this->assertSame($cuentaB, $imputacion->resolverCuenta($empresaId, $provId, '0001'));
        $this->assertCount(1, $imputacion->puntosVenta($empresaId, $provId));
    }

    public function test_compra_sin_cuenta_explicita_toma_el_default_del_proveedor_end_to_end(): void
    {
        // Ahora sí conectado (Parte 2): CompraService resuelve la cuenta por defecto al crear.
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];
        $cuenta = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Catamarca Combustibles', 'cuit' => '30222222229',
        ], $auth)['json']['data']['id'];

        (new SujetoEmpresaRepository($this->pdo))->setCuenta($empresaId, $provId, 'proveedor', $cuenta);

        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-15', 'proveedor_id' => $provId,
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($cuenta, (int) $resp['json']['data']['discriminaciones'][0]['cuenta_id']);
    }

    public function test_linea_con_cuenta_explicita_no_se_pisa_por_el_default_end_to_end(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];
        $default = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5000', 'nombre' => 'Gastos Generales',
        ], $auth)['json']['data']['id'];
        $manual = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5002', 'nombre' => 'Intereses por Giro en Descubierto',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Banco de la Nación', 'cuit' => '30222222229',
        ], $auth)['json']['data']['id'];

        (new SujetoEmpresaRepository($this->pdo))->setCuenta($empresaId, $provId, 'proveedor', $default);

        // Caso resumen bancario (R1): la línea trae su propia cuenta manual, distinta del
        // default del proveedor — el resolver no debe pisarla.
        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => '2026-01-15', 'proveedor_id' => $provId,
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'cuenta_id' => $manual],
            ],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($manual, (int) $resp['json']['data']['discriminaciones'][0]['cuenta_id']);
    }
}
