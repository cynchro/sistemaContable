<?php

namespace Tests\Feature;

use App\Modules\Iva\Repositories\SujetoRepository;
use App\Modules\Iva\Repositories\ConceptoRepository;
use App\Modules\Iva\Repositories\SujetoEmpresaRepository;
use App\Modules\Iva\Repositories\ImputacionContableRepository;

/**
 * Imputación contable del Padrón Único vía la capa de "concepto" global (documento "Satélite
 * Visual IVA" §5.2/§5.4, migración 0051): un mismo proveedor carga su concepto/regla de punto de
 * venta UNA sola vez para todo el estudio, y cada empresa lo traduce a su propia cuenta. Se
 * ejercitan los repositorios directamente (sin endpoints HTTP propios todavía para las reglas
 * globales — ver ImputacionContableHttpTest para la capa HTTP existente).
 */
class ImputacionContableTest extends FeatureTestCase
{
    private function conceptos(): ConceptoRepository
    {
        return new ConceptoRepository($this->pdo);
    }

    public function test_sin_regla_cargada_no_resuelve_ninguna_cuenta(): void
    {
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $provId    = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $imputacion = new ImputacionContableRepository($this->pdo);

        $this->assertNull($imputacion->resolverCuenta($empresaId, $provId, null));
        $this->assertNull($imputacion->resolverCuenta($empresaId, $provId, '0003'));
    }

    public function test_concepto_default_global_resuelto_via_mapeo_de_la_empresa(): void
    {
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $cuentaId  = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Catamarca Combustibles', 'cuit' => '30222222229',
        ], $auth)['json']['data']['id'];

        $concepto   = $this->conceptos()->create(['nombre' => 'Combustibles'], $user['tenantId']);
        $sujetos    = new SujetoRepository($this->pdo);
        $imputacion = new ImputacionContableRepository($this->pdo);

        $sujetos->update($provId, ['concepto_default_id' => $concepto['id']], $user['tenantId']);

        // Sin mapeo todavía en esta empresa: el concepto se resuelve pero no hay cuenta.
        $this->assertNull($imputacion->resolverCuenta($empresaId, $provId, null));

        $imputacion->setMapeoEmpresa($empresaId, (int) $concepto['id'], $cuentaId);

        $this->assertSame($cuentaId, $imputacion->resolverCuenta($empresaId, $provId, null));
        $this->assertSame($cuentaId, $imputacion->resolverCuenta($empresaId, $provId, '0001'));
    }

    public function test_excepcion_de_concepto_por_empresa_pisa_el_default_global(): void
    {
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $cuentaDefault = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5000', 'nombre' => 'Compras al Costo',
        ], $auth)['json']['data']['id'];
        $cuentaExcepcion = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor Combustible', 'cuit' => '30666666662',
        ], $auth)['json']['data']['id'];

        $conceptoDefault   = $this->conceptos()->create(['nombre' => 'Compras al costo'], $user['tenantId']);
        $conceptoExcepcion = $this->conceptos()->create(['nombre' => 'Combustibles'], $user['tenantId']);

        $sujetos        = new SujetoRepository($this->pdo);
        $sujetoEmpresas = new SujetoEmpresaRepository($this->pdo);
        $imputacion     = new ImputacionContableRepository($this->pdo);

        $sujetos->update($provId, ['concepto_default_id' => $conceptoDefault['id']], $user['tenantId']);
        $imputacion->setMapeoEmpresa($empresaId, (int) $conceptoDefault['id'], $cuentaDefault);
        $imputacion->setMapeoEmpresa($empresaId, (int) $conceptoExcepcion['id'], $cuentaExcepcion);

        // Sin excepción todavía: cae al default global.
        $this->assertSame($cuentaDefault, $imputacion->resolverCuenta($empresaId, $provId, null));

        // Ejemplo del documento §5.2: el mismo proveedor, pero en ESTA empresa se revende
        // (consumo distinto al genérico) — excepción por contribuyente.
        $sujetoEmpresas->setConcepto($empresaId, $provId, 'proveedor', (int) $conceptoExcepcion['id']);

        $this->assertSame($cuentaExcepcion, $imputacion->resolverCuenta($empresaId, $provId, null));
    }

    public function test_regla_global_de_punto_de_venta_aplica_a_cualquier_empresa(): void
    {
        // Caso MUCHAY SRL (documento §5.4): un mismo proveedor factura combustible desde el
        // PV 0003 — la regla se carga UNA vez y aplica a todas las empresas del estudio.
        $user     = $this->actingAsUser();
        $auth     = $this->bearer($user['token']);
        $empresaA = (int) $this->postJson('/empresas', ['nombre' => 'Empresa A'], $auth)['json']['data']['id'];
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $cuentaA  = (int) $this->postJson("/empresas/{$empresaA}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $cuentaB = (int) $this->postJson("/empresas/{$empresaB}/cuentas", [
            'codigo' => '6001', 'nombre' => 'Combustible (otro plan de cuentas)',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaA}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30444444440',
        ], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaB}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30444444440',
        ], $auth);

        $concepto   = $this->conceptos()->create(['nombre' => 'Combustibles'], $user['tenantId']);
        $imputacion = new ImputacionContableRepository($this->pdo);

        $imputacion->setReglaGlobal($provId, '0003', (int) $concepto['id']);
        $imputacion->setMapeoEmpresa($empresaA, (int) $concepto['id'], $cuentaA);
        $imputacion->setMapeoEmpresa($empresaB, (int) $concepto['id'], $cuentaB);

        // Misma regla global, cada empresa la traduce a SU propia cuenta.
        $this->assertSame($cuentaA, $imputacion->resolverCuenta($empresaA, $provId, '0003'));
        $this->assertSame($cuentaB, $imputacion->resolverCuenta($empresaB, $provId, '0003'));

        // Otro PV sin regla: no resuelve nada (no hay default cargado en este test).
        $this->assertNull($imputacion->resolverCuenta($empresaA, $provId, '0004'));

        $reglas = $imputacion->reglasGlobales($empresaA, $provId);
        $this->assertCount(1, $reglas);
        $this->assertSame('0003', $reglas[0]['punto_venta']);
        $this->assertSame($cuentaA, (int) $reglas[0]['cuenta_id']);
    }

    public function test_excepcion_de_punto_de_venta_por_empresa_pisa_la_regla_global(): void
    {
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $cuentaGlobal = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $cuentaExcepcion = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5002', 'nombre' => 'Insumos Varios',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30888888884',
        ], $auth)['json']['data']['id'];

        $conceptoGlobal    = $this->conceptos()->create(['nombre' => 'Combustibles'], $user['tenantId']);
        $conceptoExcepcion = $this->conceptos()->create(['nombre' => 'Insumos'], $user['tenantId']);
        $imputacion        = new ImputacionContableRepository($this->pdo);

        $imputacion->setReglaGlobal($provId, '0003', (int) $conceptoGlobal['id']);
        $imputacion->setMapeoEmpresa($empresaId, (int) $conceptoGlobal['id'], $cuentaGlobal);
        $imputacion->setMapeoEmpresa($empresaId, (int) $conceptoExcepcion['id'], $cuentaExcepcion);

        $this->assertSame($cuentaGlobal, $imputacion->resolverCuenta($empresaId, $provId, '0003'));

        $imputacion->setReglaEmpresa($empresaId, $provId, '0003', (int) $conceptoExcepcion['id']);
        $this->assertSame($cuentaExcepcion, $imputacion->resolverCuenta($empresaId, $provId, '0003'));

        $reglas = $imputacion->reglasEmpresa($empresaId, $provId);
        $this->assertCount(1, $reglas);
        $imputacion->deleteReglaEmpresa((int) $reglas[0]['id'], $empresaId);

        // Borrada la excepción, vuelve a la regla global.
        $this->assertSame($cuentaGlobal, $imputacion->resolverCuenta($empresaId, $provId, '0003'));
    }

    public function test_reasignar_regla_global_actualiza_el_concepto(): void
    {
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación SA'], $auth)['json']['data']['id'];
        $cuentaA   = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Cuenta A',
        ], $auth)['json']['data']['id'];
        $cuentaB = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5002', 'nombre' => 'Cuenta B',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor X', 'cuit' => '30999999995',
        ], $auth)['json']['data']['id'];

        $conceptoA  = $this->conceptos()->create(['nombre' => 'Concepto A'], $user['tenantId']);
        $conceptoB  = $this->conceptos()->create(['nombre' => 'Concepto B'], $user['tenantId']);
        $imputacion = new ImputacionContableRepository($this->pdo);
        $imputacion->setMapeoEmpresa($empresaId, (int) $conceptoA['id'], $cuentaA);
        $imputacion->setMapeoEmpresa($empresaId, (int) $conceptoB['id'], $cuentaB);

        $imputacion->setReglaGlobal($provId, '0001', (int) $conceptoA['id']);
        $imputacion->setReglaGlobal($provId, '0001', (int) $conceptoB['id']);

        $this->assertSame($cuentaB, $imputacion->resolverCuenta($empresaId, $provId, '0001'));
        $this->assertCount(1, $imputacion->reglasGlobales($empresaId, $provId));
    }

    public function test_compra_sin_cuenta_explicita_toma_el_default_del_proveedor_end_to_end(): void
    {
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
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

        $concepto   = $this->conceptos()->create(['nombre' => 'Combustibles'], $user['tenantId']);
        $imputacion = new ImputacionContableRepository($this->pdo);
        $imputacion->setMapeoEmpresa($empresaId, (int) $concepto['id'], $cuenta);
        (new SujetoRepository($this->pdo))->update(
            $provId,
            ['concepto_default_id' => $concepto['id']],
            $user['tenantId'],
        );

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
        $user      = $this->actingAsUser();
        $auth      = $this->bearer($user['token']);
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

        $concepto   = $this->conceptos()->create(['nombre' => 'Gastos Generales'], $user['tenantId']);
        $imputacion = new ImputacionContableRepository($this->pdo);
        $imputacion->setMapeoEmpresa($empresaId, (int) $concepto['id'], $default);
        (new SujetoRepository($this->pdo))->update(
            $provId,
            ['concepto_default_id' => $concepto['id']],
            $user['tenantId'],
        );

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
