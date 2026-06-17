<?php

namespace Tests\Feature;

/**
 * ABM de tipos de retención/percepción por tenant: el estudio ve las estándar de AFIP
 * (tenant_id NULL) + las propias, pero solo puede editar/borrar las propias.
 */
class TipoRetencionCrudTest extends FeatureTestCase
{
    public function test_lista_globales_y_propios_y_abm_solo_propios(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        // Sembrar una estándar de AFIP (tenant_id NULL → global, read-only).
        $this->pdo->exec(
            "INSERT INTO tipos_retencion (id, cod_afip, alicuota, nombre, tipo_rg3685)
             VALUES (100, '3', 0, 'Percepcion IIBB', 3)"
        );

        $this->assertCount(1, $this->getJson('/tipos-retencion', $auth)['json']['data']);

        // La estándar no se puede editar ni borrar (no es del estudio) → 404.
        $this->assertSame(404, $this->putJson('/tipos-retencion/100', ['nombre' => 'x'], $auth)['status']);
        $this->assertSame(404, $this->deleteJson('/tipos-retencion/100', $auth)['status']);

        // Crear una propia → 201; aparece junto a la global.
        $created = $this->postJson('/tipos-retencion', ['nombre' => 'Percepción propia', 'alicuota' => '2.5'], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertCount(2, $this->getJson('/tipos-retencion', $auth)['json']['data']);

        // La propia sí se puede editar y borrar.
        $upd = $this->putJson("/tipos-retencion/{$id}", ['nombre' => 'Editada'], $auth);
        $this->assertSame(200, $upd['status']);
        $this->assertSame('Editada', $upd['json']['data']['nombre']);
        $this->assertSame(200, $this->deleteJson("/tipos-retencion/{$id}", $auth)['status']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        $aliceAuth = $this->bearer($this->actingAsUser()['token']);
        $idA = (int) $this->postJson('/tipos-retencion', ['nombre' => 'De Alice'], $aliceAuth)['json']['data']['id'];

        $bobAuth = $this->bearer($this->actingAsUser()['token']);
        // Bob no ve la de Alice (no hay globales sembradas en test) ni la puede tocar.
        $this->assertCount(0, $this->getJson('/tipos-retencion', $bobAuth)['json']['data']);
        $this->assertSame(404, $this->putJson("/tipos-retencion/{$idA}", ['nombre' => 'x'], $bobAuth)['status']);
    }
}
