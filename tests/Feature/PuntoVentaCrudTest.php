<?php

namespace Tests\Feature;

/**
 * ABM de puntos de venta por empresa (numeración de factura electrónica):
 * CRUD, unicidad del número por empresa y aislamiento por tenant.
 */
class PuntoVentaCrudTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, e:int} */
    private function escenario(): array
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'PV SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'e' => $e];
    }

    public function test_crud_completo(): void
    {
        ['auth' => $auth, 'e' => $e] = $this->escenario();

        $created = $this->postJson("/empresas/{$e}/puntos-venta", [
            'numero'      => 1,
            'descripcion' => 'Casa central',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('CAE', $created['json']['data']['tipo_emision']);

        $this->assertCount(1, $this->getJson("/empresas/{$e}/puntos-venta", $auth)['json']['data']);

        $upd = $this->putJson(
            "/empresas/{$e}/puntos-venta/{$id}",
            ['descripcion' => 'Sucursal', 'activo' => 'N'],
            $auth,
        );
        $this->assertSame(200, $upd['status']);
        $this->assertSame('Sucursal', $upd['json']['data']['descripcion']);
        $this->assertSame('N', $upd['json']['data']['activo']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/puntos-venta/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$e}/puntos-venta/{$id}", $auth)['status']);
    }

    public function test_numero_requerido(): void
    {
        ['auth' => $auth, 'e' => $e] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/puntos-venta", ['descripcion' => 'X'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('numero', $resp['json']['errors']);
    }

    public function test_numero_duplicado_da_409(): void
    {
        ['auth' => $auth, 'e' => $e] = $this->escenario();
        $this->postJson("/empresas/{$e}/puntos-venta", ['numero' => 5], $auth);

        $resp = $this->postJson("/empresas/{$e}/puntos-venta", ['numero' => 5], $auth);

        $this->assertSame(409, $resp['status']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['auth' => $auth, 'e' => $e] = $this->escenario();
        $otroAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/puntos-venta", $otroAuth)['status']);
    }
}
