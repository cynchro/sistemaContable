<?php

namespace Tests\Feature;

/**
 * Aislamiento multi-tenant a nivel HTTP: un tenant no ve ni alcanza los datos de
 * otro, porque los repos filtran por el `tenant_id` que resuelve TenantMiddleware
 * desde el JWT (no desde el input del cliente).
 */
class TenantIsolationTest extends FeatureTestCase
{
    public function test_tenant_cannot_see_other_tenants_data(): void
    {
        $alice = $this->actingAsUser();
        $bob   = $this->actingAsUser();

        // Alice crea una empresa en su tenant.
        $created = $this->postJson('/empresas', ['nombre' => 'Empresa de Alice'], $this->bearer($alice['token']));
        $this->assertSame(201, $created['status']);

        // Bob lista: no ve nada de Alice.
        $bobList = $this->getJson('/empresas', $this->bearer($bob['token']));
        $this->assertSame(200, $bobList['status']);
        $this->assertCount(0, $bobList['json']['data']);

        // Alice sí lo ve.
        $aliceList = $this->getJson('/empresas', $this->bearer($alice['token']));
        $this->assertCount(1, $aliceList['json']['data']);
    }

    public function test_tenant_cannot_access_other_tenants_record_by_id(): void
    {
        $alice = $this->actingAsUser();
        $bob   = $this->actingAsUser();

        $created = $this->postJson('/empresas', ['nombre' => 'Privada'], $this->bearer($alice['token']));
        $id = (int) $created['json']['data']['id'];

        // Bob intenta leer el id de Alice → 404 (no existe en su tenant).
        $bobShow = $this->getJson("/empresas/{$id}", $this->bearer($bob['token']));
        $this->assertSame(404, $bobShow['status']);

        // Bob intenta borrarlo → tampoco afecta nada de Alice.
        $this->deleteJson("/empresas/{$id}", $this->bearer($bob['token']));
        $aliceShow = $this->getJson("/empresas/{$id}", $this->bearer($alice['token']));
        $this->assertSame(200, $aliceShow['status']);
    }
}
