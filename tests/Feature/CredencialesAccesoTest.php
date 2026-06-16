<?php

namespace Tests\Feature;

/**
 * Credenciales de acceso del contribuyente: CRUD + cifrado de la clave en reposo.
 */
class CredencialesAccesoTest extends FeatureTestCase
{
    public function test_credencial_crud_y_clave_cifrada_en_reposo(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'ACME SA'], $auth)['json']['data']['id'];

        $created = $this->postJson("/empresas/{$e}/credenciales", [
            'tipo'    => 'fiscal',
            'sistema' => 'AFIP',
            'usuario' => '20111111112',
            'clave'   => 'secreto-afip',
        ], $auth);

        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        // La API devuelve la clave en claro (el estudio la necesita).
        $this->assertSame('secreto-afip', $created['json']['data']['clave']);

        // Pero en la base está cifrada (nunca en texto plano).
        $stored = $this->pdo->query("SELECT clave FROM credenciales_acceso WHERE id = {$id}")->fetchColumn();
        $this->assertStringStartsWith('enc:v1:', (string) $stored);
        $this->assertStringNotContainsString('secreto-afip', (string) $stored);

        $this->assertCount(1, $this->getJson("/empresas/{$e}/credenciales", $auth)['json']['data']);

        $upd = $this->putJson("/empresas/{$e}/credenciales/{$id}", [
            'clave'  => 'nueva-clave',
            'estado' => 'bloqueada',
        ], $auth);
        $this->assertSame(200, $upd['status']);
        $this->assertSame('nueva-clave', $upd['json']['data']['clave']);
        $this->assertSame('bloqueada', $upd['json']['data']['estado']);

        $fetched = $this->getJson("/empresas/{$e}/credenciales/{$id}", $auth);
        $this->assertSame('nueva-clave', $fetched['json']['data']['clave']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/credenciales/{$id}", $auth)['status']);
    }

    public function test_sistema_requerido_falla(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'X'], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$e}/credenciales", ['usuario' => 'u'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('sistema', $resp['json']['errors']);
    }

    public function test_tipo_invalido_falla(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'X'], $auth)['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$e}/credenciales", [
            'sistema' => 'AFIP',
            'tipo'    => 'no-existe',
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('tipo', $resp['json']['errors']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'ACME'], $auth)['json']['data']['id'];
        $otroAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/credenciales", $otroAuth)['status']);
    }
}
