<?php

namespace Tests\Feature;

/**
 * Módulo Tareas (de sistemaCuarto): tipos, workflow de estado con historial y
 * comentarios. Tenant-scoped; empresa (contribuyente) opcional.
 */
class TareasTest extends FeatureTestCase
{
    public function test_tipos_crud(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $created = $this->postJson('/tareas/tipos', ['nombre' => 'Presentación DDJJ', 'categoria' => 'fiscal'], $auth);
        $this->assertSame(201, $created['status']);
        $this->assertCount(1, $this->getJson('/tareas/tipos', $auth)['json']['data']);
    }

    public function test_tarea_workflow_estado_historial_y_comentarios(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $created = $this->postJson('/tareas', [
            'titulo'    => 'Armar DDJJ IVA',
            'prioridad' => 'alta',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('pendiente', $created['json']['data']['estado']);

        // Cambiar estado (registra historial).
        $est = $this->putJson("/tareas/{$id}/estado", ['estado' => 'en_progreso', 'observacion' => 'Arranco'], $auth);
        $this->assertSame(200, $est['status']);
        $this->assertSame('en_progreso', $est['json']['data']['estado']);

        $hist = $this->getJson("/tareas/{$id}/historial", $auth);
        // Creación (pendiente) + cambio (en_progreso) = 2 registros.
        $this->assertCount(2, $hist['json']['data']);
        $this->assertSame('pendiente', $hist['json']['data'][1]['estado_anterior']);
        $this->assertSame('en_progreso', $hist['json']['data'][1]['estado_nuevo']);

        // Comentario.
        $com = $this->postJson("/tareas/{$id}/comentarios", ['comentario' => 'Falta doc del cliente'], $auth);
        $this->assertSame(200, $com['status']);
        $this->assertCount(1, $com['json']['data']);

        // Estado inválido → 422.
        $bad = $this->putJson("/tareas/{$id}/estado", ['estado' => 'inexistente'], $auth);
        $this->assertSame(422, $bad['status']);
    }

    public function test_prioridad_invalida_falla(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->postJson('/tareas', ['titulo' => 'X', 'prioridad' => 'altisima'], $auth);
        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('prioridad', $resp['json']['errors']);
    }

    public function test_empresa_de_otro_tenant_da_404_y_aislamiento(): void
    {
        $alice = $this->bearer($this->actingAsUser()['token']);
        $bob   = $this->bearer($this->actingAsUser()['token']);

        // Empresa de Bob.
        $empBob = (int) $this->postJson('/empresas', ['nombre' => 'De Bob'], $bob)['json']['data']['id'];

        // Alice intenta crear tarea referida a la empresa de Bob → 404.
        $resp = $this->postJson('/tareas', ['titulo' => 'T', 'empresa_id' => $empBob], $alice);
        $this->assertSame(404, $resp['status']);

        // Aislamiento de tareas.
        $this->postJson('/tareas', ['titulo' => 'De Alice'], $alice);
        $this->assertCount(0, $this->getJson('/tareas', $bob)['json']['data']);
    }
}
