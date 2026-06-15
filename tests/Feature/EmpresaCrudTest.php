<?php

namespace Tests\Feature;

/**
 * CRUD del vertical de empresas (módulo Compartido), acotado al tenant=estudio.
 */
class EmpresaCrudTest extends FeatureTestCase
{
    public function test_crud_completo_de_empresa(): void
    {
        $user = $this->actingAsUser();
        $auth = $this->bearer($user['token']);

        // Crear
        $created = $this->postJson('/empresas', [
            'nombre'    => 'ACME SA',
            'cuit'      => '30712345678',
            'localidad' => 'Córdoba',
            'exporta'   => 'N',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('ACME SA', $created['json']['data']['nombre']);

        // Listar
        $list = $this->getJson('/empresas', $auth);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, $list['json']['data']);

        // Ver
        $show = $this->getJson("/empresas/{$id}", $auth);
        $this->assertSame(200, $show['status']);
        $this->assertSame('30712345678', $show['json']['data']['cuit']);

        // Modificar
        $updated = $this->putJson("/empresas/{$id}", [
            'nombre'    => 'ACME S.A.',
            'localidad' => 'Rosario',
            'exporta'   => 'S',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('ACME S.A.', $updated['json']['data']['nombre']);
        $this->assertSame('Rosario', $updated['json']['data']['localidad']);
        $this->assertSame('S', $updated['json']['data']['exporta']);

        // Borrar
        $deleted = $this->deleteJson("/empresas/{$id}", $auth);
        $this->assertSame(200, $deleted['status']);

        // Ya no existe
        $gone = $this->getJson("/empresas/{$id}", $auth);
        $this->assertSame(404, $gone['status']);
    }

    public function test_crear_empresa_sin_nombre_falla_validacion(): void
    {
        $user = $this->actingAsUser();

        $resp = $this->postJson('/empresas', ['cuit' => '20111111119'], $this->bearer($user['token']));

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombre', $resp['json']['errors']);
    }

    public function test_empresas_requiere_autenticacion(): void
    {
        $resp = $this->getJson('/empresas');

        $this->assertSame(401, $resp['status']);
    }
}
