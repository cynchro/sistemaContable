<?php

namespace Tests\Feature;

/**
 * CRUD del módulo de scaffolding `clientes` contra DB real: ejercita el SQL de
 * ClienteRepository (create/findAll/findById/update/delete), la validación de
 * los FormRequest y el pipeline Auth + Tenant.
 */
class ClienteCrudTest extends FeatureTestCase
{
    public function test_full_crud_cycle(): void
    {
        $ctx  = $this->actingAsUser();
        $auth = $this->bearer($ctx['token']);

        // create
        $created = $this->postJson('/clientes', ['nombre' => 'Acme SA'], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('Acme SA', $created['json']['data']['nombre']);

        // index
        $list = $this->getJson('/clientes', $auth);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, $list['json']['data']);

        // show
        $show = $this->getJson("/clientes/{$id}", $auth);
        $this->assertSame(200, $show['status']);
        $this->assertSame('Acme SA', $show['json']['data']['nombre']);

        // update
        $updated = $this->putJson("/clientes/{$id}", ['nombre' => 'Acme Corp'], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertTrue($updated['json']['data']['updated']);

        $showAfter = $this->getJson("/clientes/{$id}", $auth);
        $this->assertSame('Acme Corp', $showAfter['json']['data']['nombre']);

        // delete
        $deleted = $this->deleteJson("/clientes/{$id}", $auth);
        $this->assertSame(200, $deleted['status']);

        $missing = $this->getJson("/clientes/{$id}", $auth);
        $this->assertSame(404, $missing['status']);
    }

    public function test_create_requires_nombre(): void
    {
        $ctx = $this->actingAsUser();

        $res = $this->postJson('/clientes', [], $this->bearer($ctx['token']));

        $this->assertSame(422, $res['status']);
    }

    public function test_requires_authentication(): void
    {
        $res = $this->getJson('/clientes');

        $this->assertSame(401, $res['status']);
    }
}
