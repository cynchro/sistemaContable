<?php

namespace Tests\Feature;

/**
 * Capa HTTP de las reglas de imputación contable del proveedor (Pantalla B, página aparte —
 * decisión B2, ver documentacion/analisis-satelite-visual-iva.md §8/§10). Migración 0051
 * reescribió el modelo con una capa de "concepto" (tenant-level) — el repositorio se prueba en
 * ImputacionContableTest; acá se prueba el Service/Controller/rutas con las 4 secciones que
 * expone `ProveedorImputacionPage`: regla global de PV, excepción de PV por empresa, excepción
 * del concepto por defecto, y mapeo concepto→cuenta de la empresa.
 */
class ImputacionContableHttpTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: string, 2: int, 3: int} [auth, tenantId, empresaId, proveedorId] */
    private function empresaConProveedor(array $user): array
    {
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación HTTP SA'], $auth)
            ['json']['data']['id'];
        $proveedorId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Distribuidora SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        return [$auth, $user['tenantId'], $empresaId, $proveedorId];
    }

    private function concepto(array $auth, string $nombre): int
    {
        return (int) $this->postJson('/iva/conceptos', ['nombre' => $nombre], $auth)['json']['data']['id'];
    }

    public function test_mapeo_concepto_cuenta_de_la_empresa(): void
    {
        [$auth, , $empresaId] = $this->empresaConProveedor($this->actingAsUser());
        $conceptoId = $this->concepto($auth, 'Combustibles');
        $cuentaId   = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaId}/conceptos-cuenta", [
            'concepto_id' => $conceptoId, 'cuenta_id' => $cuentaId,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lista = $this->getJson("/empresas/{$empresaId}/conceptos-cuenta", $auth);
        $this->assertCount(1, $lista['json']['data']);
        $this->assertSame($cuentaId, (int) $lista['json']['data'][0]['cuenta_id']);

        $del = $this->deleteJson("/empresas/{$empresaId}/conceptos-cuenta/{$conceptoId}", $auth);
        $this->assertSame(200, $del['status']);
        $this->assertCount(0, $this->getJson("/empresas/{$empresaId}/conceptos-cuenta", $auth)['json']['data']);
    }

    public function test_crea_lista_y_borra_regla_global_de_punto_de_venta(): void
    {
        [$auth, , $empresaId, $proveedorId] = $this->empresaConProveedor($this->actingAsUser());
        $conceptoId = $this->concepto($auth, 'Combustibles');

        $resp = $this->postJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/global", [
            'punto_venta' => '4', 'concepto_id' => $conceptoId,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lista = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/global", $auth);
        $this->assertCount(1, $lista['json']['data']);
        $this->assertSame('4', $lista['json']['data'][0]['punto_venta']);
        $this->assertSame($conceptoId, (int) $lista['json']['data'][0]['concepto_id']);

        $id  = (int) $lista['json']['data'][0]['id'];
        $del = $this->deleteJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/global/{$id}", $auth);
        $this->assertSame(200, $del['status']);
        $vacia = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/global", $auth);
        $this->assertCount(0, $vacia['json']['data']);
    }

    public function test_regla_global_visible_desde_otra_empresa_del_mismo_tenant(): void
    {
        $user        = $this->actingAsUser();
        [$auth, , $empresaA, $proveedorId] = $this->empresaConProveedor($user);
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $conceptoId = $this->concepto($auth, 'Combustibles');

        $this->postJson("/empresas/{$empresaA}/proveedores/{$proveedorId}/imputacion/global", [
            'punto_venta' => '4', 'concepto_id' => $conceptoId,
        ], $auth);

        // La regla es del proveedor (tenant), no de la empresa A puntual: se ve desde la B sin
        // que el proveedor esté activado ahí (documento §5.4, "aplica a todas las empresas").
        $lista = $this->getJson("/empresas/{$empresaB}/proveedores/{$proveedorId}/imputacion/global", $auth);
        $this->assertSame(200, $lista['status']);
        $this->assertCount(1, $lista['json']['data']);
    }

    public function test_excepcion_de_punto_de_venta_por_empresa(): void
    {
        [$auth, , $empresaId, $proveedorId] = $this->empresaConProveedor($this->actingAsUser());
        $conceptoId = $this->concepto($auth, 'Insumos');

        $resp = $this->postJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/empresa", [
            'punto_venta' => '4', 'concepto_id' => $conceptoId,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lista = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/empresa", $auth);
        $this->assertCount(1, $lista['json']['data']);

        $id = (int) $lista['json']['data'][0]['id'];
        $this->deleteJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/empresa/{$id}", $auth);
        $vacia = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/empresa", $auth);
        $this->assertCount(0, $vacia['json']['data']);
    }

    public function test_excepcion_del_concepto_por_defecto(): void
    {
        [$auth, , $empresaId, $proveedorId] = $this->empresaConProveedor($this->actingAsUser());
        $conceptoId = $this->concepto($auth, 'Compras al costo');

        $vacio = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/concepto-default", $auth);
        $this->assertNull($vacio['json']['data']['concepto_id']);

        $resp = $this->putJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/concepto-default", [
            'concepto_id' => $conceptoId,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lleno = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/concepto-default", $auth);
        $this->assertSame($conceptoId, $lleno['json']['data']['concepto_id']);
    }

    public function test_concepto_de_otra_empresa_no_aplica_pero_cuenta_de_otra_empresa_da_422_en_mapeo(): void
    {
        $user      = $this->actingAsUser();
        [$auth, , $empresaA] = $this->empresaConProveedor($user);
        $empresaB  = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $cuentaDeB = (int) $this->postJson("/empresas/{$empresaB}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Cuenta de B',
        ], $auth)['json']['data']['id'];
        $conceptoId = $this->concepto($auth, 'Combustibles');

        $resp = $this->postJson("/empresas/{$empresaA}/conceptos-cuenta", [
            'concepto_id' => $conceptoId, 'cuenta_id' => $cuentaDeB,
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuenta_id', $resp['json']['errors']);
    }

    public function test_proveedor_inexistente_da_404(): void
    {
        [$auth, , $empresaId] = $this->empresaConProveedor($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/proveedores/999999/imputacion/global", [
            'punto_venta' => '4', 'concepto_id' => 1,
        ], $auth);

        $this->assertSame(404, $resp['status']);
    }
}
