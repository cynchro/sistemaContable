<?php

namespace Tests\Feature;

/**
 * Cuenta contable por defecto de un proveedor en una empresa (documento "Satélite Visual IVA"
 * §5, Pantalla A del panorama de UI — ver documentacion/analisis-satelite-visual-iva.md §8).
 * Vive en `iva_sujeto_empresas.cuenta_id` (Parte 1, migración 0049); acá se prueba el único
 * punto de entrada HTTP: el PUT de alta/edición de proveedor.
 */
class SujetoCuentaPorDefectoTest extends FeatureTestCase
{
    public function test_actualizar_proveedor_con_cuenta_id_la_persiste_y_la_devuelve(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Cuenta Default SA'], $auth)['json']['data']['id'];
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Combustibles y Lubricantes',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $resp = $this->putJson("/empresas/{$empresaId}/proveedores/{$provId}", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118', 'cuenta_id' => $cuentaId,
        ], $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame($cuentaId, $resp['json']['data']['cuenta_id']);

        // El GET (show) y el listado reflejan la cuenta persistida.
        $show = $this->getJson("/empresas/{$empresaId}/proveedores/{$provId}", $auth);
        $this->assertSame($cuentaId, $show['json']['data']['cuenta_id']);
        $lista = $this->getJson("/empresas/{$empresaId}/proveedores", $auth);
        $this->assertSame($cuentaId, $lista['json']['data'][0]['cuenta_id']);
    }

    public function test_cuenta_id_de_otra_empresa_da_422(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaA = (int) $this->postJson('/empresas', ['nombre' => 'Empresa A'], $auth)['json']['data']['id'];
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $cuentaDeB = (int) $this->postJson("/empresas/{$empresaB}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Cuenta de B',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaA}/proveedores", [
            'nombre' => 'Proveedor A', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $resp = $this->putJson("/empresas/{$empresaA}/proveedores/{$provId}", [
            'nombre' => 'Proveedor A', 'cuit' => '30111111118', 'cuenta_id' => $cuentaDeB,
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuenta_id', $resp['json']['errors']);
    }

    public function test_cuenta_id_null_borra_la_regla(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Cuenta Default SA'], $auth)['json']['data']['id'];
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '5001', 'nombre' => 'Gastos Generales',
        ], $auth)['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor X', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $this->putJson("/empresas/{$empresaId}/proveedores/{$provId}", [
            'nombre' => 'Proveedor X', 'cuit' => '30111111118', 'cuenta_id' => $cuentaId,
        ], $auth);

        $resp = $this->putJson("/empresas/{$empresaId}/proveedores/{$provId}", [
            'nombre' => 'Proveedor X', 'cuit' => '30111111118', 'cuenta_id' => null,
        ], $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertNull($resp['json']['data']['cuenta_id']);
    }
}
