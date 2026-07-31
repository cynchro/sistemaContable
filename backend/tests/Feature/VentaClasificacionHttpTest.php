<?php

namespace Tests\Feature;

/**
 * Capa HTTP del motor de clasificación de ventas por punto de venta + tipo de comprobante
 * (Pantalla D, ver documentacion/analisis-satelite-visual-iva.md §8). El repositorio y su
 * lógica de resolución ya se prueban en VentaClasificacionTest (Parte 5) — acá se prueba el
 * Service/Controller/rutas nuevos (antes no existían, solo el repositorio).
 */
class VentaClasificacionHttpTest extends FeatureTestCase
{
    public function test_crea_lista_y_borra_regla_por_punto_de_venta(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson(
            '/empresas',
            ['nombre' => 'Clasificación HTTP SA'],
            $auth,
        )['json']['data']['id'];
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4001', 'nombre' => 'Ventas de Servicios',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaId}/ventas-punto-venta", [
            'punto_venta' => '3', 'cuenta_id' => $cuentaId,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lista = $this->getJson("/empresas/{$empresaId}/ventas-punto-venta", $auth);
        $this->assertCount(1, $lista['json']['data']);
        $this->assertSame('3', $lista['json']['data'][0]['punto_venta']);
        $this->assertSame($cuentaId, (int) $lista['json']['data'][0]['cuenta_id']);

        $id = (int) $lista['json']['data'][0]['id'];
        $del = $this->deleteJson("/empresas/{$empresaId}/ventas-punto-venta/{$id}", $auth);
        $this->assertSame(200, $del['status']);
        $this->assertCount(0, $this->getJson("/empresas/{$empresaId}/ventas-punto-venta", $auth)['json']['data']);
    }

    public function test_crea_lista_y_borra_excepcion_por_tipo_de_comprobante(): void
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (10, 'NC', 'Nota de Crédito A', -1)"
        );

        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson(
            '/empresas',
            ['nombre' => 'Clasificación HTTP SA'],
            $auth,
        )['json']['data']['id'];
        $cuenta = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4002', 'nombre' => 'Notas de Crédito por Ventas',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaId}/ventas-punto-venta-tipo", [
            'punto_venta' => '3', 'tipo_comprobante_id' => 10, 'cuenta_id' => $cuenta,
        ], $auth);
        $this->assertSame(200, $resp['status']);

        $lista = $this->getJson("/empresas/{$empresaId}/ventas-punto-venta-tipo", $auth);
        $this->assertCount(1, $lista['json']['data']);
        $this->assertSame(10, (int) $lista['json']['data'][0]['tipo_comprobante_id']);

        $id = (int) $lista['json']['data'][0]['id'];
        $this->deleteJson("/empresas/{$empresaId}/ventas-punto-venta-tipo/{$id}", $auth);
        $this->assertCount(0, $this->getJson("/empresas/{$empresaId}/ventas-punto-venta-tipo", $auth)['json']['data']);
    }

    public function test_cuenta_de_otra_empresa_da_422(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaA = (int) $this->postJson('/empresas', ['nombre' => 'Empresa A'], $auth)['json']['data']['id'];
        $empresaB = (int) $this->postJson('/empresas', ['nombre' => 'Empresa B'], $auth)['json']['data']['id'];
        $cuentaDeB = (int) $this->postJson("/empresas/{$empresaB}/cuentas", [
            'codigo' => '4001', 'nombre' => 'Cuenta de B',
        ], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$empresaA}/ventas-punto-venta", [
            'punto_venta' => '3', 'cuenta_id' => $cuentaDeB,
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuenta_id', $resp['json']['errors']);
    }
}
