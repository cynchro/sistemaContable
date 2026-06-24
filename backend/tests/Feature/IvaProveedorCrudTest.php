<?php

namespace Tests\Feature;

/**
 * Proveedores del módulo IVA: CRUD anidado bajo empresa, acotado al tenant dueño.
 */
class IvaProveedorCrudTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int} [auth, empresaId] */
    private function empresaDe(array $user): array
    {
        $auth    = $this->bearer($user['token']);
        $empresa = $this->postJson('/empresas', ['nombre' => 'ACME SA'], $auth);

        return [$auth, (int) $empresa['json']['data']['id']];
    }

    public function test_crud_completo(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $created = $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre'    => 'Distribuidora SRL',
            'cuit'      => '30222222223',
            'cp'        => '5000',
            'cai'       => '12345678901234',
            'fecha_cai' => '2026-12-31',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('5000', $created['json']['data']['cp']);

        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/proveedores", $auth)['json']['data']);

        $updated = $this->putJson("/empresas/{$empresaId}/proveedores/{$id}", [
            'nombre' => 'Distribuidora del Centro SRL',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Distribuidora del Centro SRL', $updated['json']['data']['nombre']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/proveedores/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$empresaId}/proveedores/{$id}", $auth)['status']);
    }

    public function test_crear_sin_nombre_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/proveedores", ['cuit' => '30222222223'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombre', $resp['json']['errors']);
    }

    public function test_no_se_acceden_proveedores_de_empresa_de_otro_tenant(): void
    {
        [, $aliceEmpresaId] = $this->empresaDe($this->actingAsUser());
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson("/empresas/{$aliceEmpresaId}/proveedores", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }

    public function test_rubro_de_otro_tenant_da_422(): void
    {
        // Bob crea un rubro en su tenant.
        $bobAuth = $this->bearer($this->actingAsUser()['token']);
        $rubroBob = (int) $this->postJson('/rubros', ['nombre' => 'De Bob'], $bobAuth)['json']['data']['id'];

        // Alice intenta usar el rubro de Bob al crear un proveedor → 422 (otro tenant).
        [$aliceAuth, $empresaId] = $this->empresaDe($this->actingAsUser());
        $resp = $this->postJson("/empresas/{$empresaId}/proveedores", [
            'nombre'   => 'Proveedor X',
            'rubro_id' => $rubroBob,
        ], $aliceAuth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('rubro_id', $resp['json']['errors']);
    }
}
