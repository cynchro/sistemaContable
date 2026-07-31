<?php

namespace Tests\Feature;

/**
 * Capa HTTP de las reglas de imputación contable por punto de venta del proveedor
 * (Pantalla B, página aparte — decisión B2, ver documentacion/analisis-satelite-visual-iva.md
 * §8/§10). El repositorio (resolverCuenta/puntosVenta/setPuntoVenta/deletePuntoVenta) ya se
 * prueba en ImputacionContableTest (Parte 1) y ya está conectado a CompraService desde la
 * Parte 2 — acá se prueba el Service/Controller/rutas nuevos.
 */
class ImputacionContableHttpTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int, 2: int} [auth, empresaId, proveedorId] */
    private function empresaConProveedor(array $user): array
    {
        $auth      = $this->bearer($user['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Imputación HTTP SA'], $auth)
            ['json']['data']['id'];
        $proveedorId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Distribuidora SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        return [$auth, $empresaId, $proveedorId];
    }

    public function test_crea_lista_y_borra_regla_por_punto_de_venta(): void
    {
        [$auth, $empresaId, $proveedorId] = $this->empresaConProveedor($this->actingAsUser());
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Compras Sucursal Norte',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion", [
            'punto_venta' => '4', 'cuenta_id' => $cuentaId,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lista = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion", $auth);
        $this->assertCount(1, $lista['json']['data']);
        $this->assertSame('4', $lista['json']['data'][0]['punto_venta']);
        $this->assertSame($cuentaId, (int) $lista['json']['data'][0]['cuenta_id']);

        $id  = (int) $lista['json']['data'][0]['id'];
        $del = $this->deleteJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion/{$id}", $auth);
        $this->assertSame(200, $del['status']);
        $vacia = $this->getJson("/empresas/{$empresaId}/proveedores/{$proveedorId}/imputacion", $auth);
        $this->assertCount(0, $vacia['json']['data']);
    }

    public function test_cuenta_de_otra_empresa_da_422(): void
    {
        $user        = $this->actingAsUser();
        [$auth, $empresaA, $proveedorId] = $this->empresaConProveedor($user);
        $empresaB    = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $cuentaDeB   = (int) $this->postJson("/empresas/{$empresaB}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Cuenta de B',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaA}/proveedores/{$proveedorId}/imputacion", [
            'punto_venta' => '4', 'cuenta_id' => $cuentaDeB,
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuenta_id', $resp['json']['errors']);
    }

    public function test_proveedor_inexistente_o_no_activo_da_404(): void
    {
        [$auth, $empresaId] = $this->empresaConProveedor($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/proveedores/999999/imputacion", [
            'punto_venta' => '4', 'cuenta_id' => 1,
        ], $auth);

        $this->assertSame(404, $resp['status']);
    }
}
