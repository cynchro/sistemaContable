<?php

namespace Tests\Feature;

/**
 * Concepto por defecto de un proveedor (documento "Satélite Visual IVA" §5.2, Pantalla A del
 * panorama de UI — ver documentacion/analisis-satelite-visual-iva.md §8). Vive en
 * `iva_sujetos.concepto_default_id` (migración 0051): tenant-level, a diferencia de la vieja
 * cuenta contable directa (empresa-level) — se prueba el único punto de entrada HTTP: el
 * PUT de alta/edición de proveedor.
 */
class SujetoConceptoPorDefectoTest extends FeatureTestCase
{
    public function test_actualizar_proveedor_con_concepto_default_id_la_persiste_y_la_devuelve(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Concepto Default SA'], $auth)
            ['json']['data']['id'];
        $conceptoId = (int) $this->postJson('/iva/conceptos', ['nombre' => 'Combustibles'], $auth)
            ['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $resp = $this->putJson("/empresas/{$empresaId}/proveedores/{$provId}", [
            'nombre' => 'Muchay SRL', 'cuit' => '30111111118', 'concepto_default_id' => $conceptoId,
        ], $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame($conceptoId, $resp['json']['data']['concepto_default_id']);

        // El GET (show) y el listado reflejan el concepto persistido.
        $show = $this->getJson("/empresas/{$empresaId}/proveedores/{$provId}", $auth);
        $this->assertSame($conceptoId, $show['json']['data']['concepto_default_id']);
        $lista = $this->getJson("/empresas/{$empresaId}/proveedores", $auth);
        $this->assertSame($conceptoId, $lista['json']['data'][0]['concepto_default_id']);
    }

    public function test_concepto_de_otro_tenant_da_422(): void
    {
        $alice     = $this->actingAsUser();
        $bob       = $this->actingAsUser();
        $aliceAuth = $this->bearer($alice['token']);
        $bobAuth   = $this->bearer($bob['token']);

        $conceptoDeBob = (int) $this->postJson('/iva/conceptos', ['nombre' => 'Combustibles'], $bobAuth)
            ['json']['data']['id'];
        $empresaAlice = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Alice'], $aliceAuth)
            ['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaAlice}/proveedores", [
            'nombre' => 'Proveedor A', 'cuit' => '30111111118',
        ], $aliceAuth)['json']['data']['id'];

        $resp = $this->putJson("/empresas/{$empresaAlice}/proveedores/{$provId}", [
            'nombre' => 'Proveedor A', 'cuit' => '30111111118', 'concepto_default_id' => $conceptoDeBob,
        ], $aliceAuth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('concepto_default_id', $resp['json']['errors']);
    }

    public function test_concepto_default_id_null_borra_la_regla(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Concepto Default SA'], $auth)
            ['json']['data']['id'];
        $conceptoId = (int) $this->postJson('/iva/conceptos', ['nombre' => 'Gastos Generales'], $auth)
            ['json']['data']['id'];
        $provId = (int) $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre' => 'Proveedor X', 'cuit' => '30111111118',
        ], $auth)['json']['data']['id'];

        $this->putJson("/empresas/{$empresaId}/proveedores/{$provId}", [
            'nombre' => 'Proveedor X', 'cuit' => '30111111118', 'concepto_default_id' => $conceptoId,
        ], $auth);

        $resp = $this->putJson("/empresas/{$empresaId}/proveedores/{$provId}", [
            'nombre' => 'Proveedor X', 'cuit' => '30111111118', 'concepto_default_id' => null,
        ], $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertNull($resp['json']['data']['concepto_default_id']);
    }
}
