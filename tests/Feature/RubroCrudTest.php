<?php

namespace Tests\Feature;

/**
 * Rubros (módulo Compartido): CRUD por tenant (estudio) y aislamiento entre tenants.
 */
class RubroCrudTest extends FeatureTestCase
{
    public function test_crud_completo(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $created = $this->postJson('/rubros', ['nombre' => 'Servicios', 'codigo' => 'SRV'], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];

        $this->assertCount(1, $this->getJson('/rubros', $auth)['json']['data']);

        $updated = $this->putJson("/rubros/{$id}", ['nombre' => 'Servicios profesionales'], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Servicios profesionales', $updated['json']['data']['nombre']);

        $this->assertSame(200, $this->deleteJson("/rubros/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/rubros/{$id}", $auth)['status']);
    }

    public function test_aislamiento_entre_tenants(): void
    {
        $aliceAuth = $this->bearer($this->actingAsUser()['token']);
        $bobAuth   = $this->bearer($this->actingAsUser()['token']);

        $created = $this->postJson('/rubros', ['nombre' => 'De Alice'], $aliceAuth);
        $id = (int) $created['json']['data']['id'];

        $this->assertCount(0, $this->getJson('/rubros', $bobAuth)['json']['data']);
        $this->assertSame(404, $this->getJson("/rubros/{$id}", $bobAuth)['status']);
    }
}
