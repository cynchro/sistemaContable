<?php

namespace Tests\Feature;

/**
 * CRM del contribuyente: campos CRM en la empresa canónica + socios.
 */
class ContribuyenteCrmTest extends FeatureTestCase
{
    public function test_empresa_acepta_campos_crm(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $created = $this->postJson('/empresas', [
            'nombre'       => 'ACME SA',
            'email'        => 'contacto@acme.com',
            'contacto'     => 'Juan Pérez',
            'tipo_persona' => 'juridica',
            'inscripcion'  => 'RI',
        ], $auth);

        $this->assertSame(201, $created['status']);
        $this->assertSame('contacto@acme.com', $created['json']['data']['email']);
        $this->assertSame('Juan Pérez', $created['json']['data']['contacto']);
        $this->assertSame('juridica', $created['json']['data']['tipo_persona']);
    }

    public function test_email_invalido_falla(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->postJson('/empresas', ['nombre' => 'X', 'email' => 'no-es-email'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('email', $resp['json']['errors']);
    }

    public function test_socios_crud(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'ACME SA'], $auth)['json']['data']['id'];

        $created = $this->postJson("/empresas/{$e}/socios", [
            'nombre'        => 'María Gómez',
            'cuit'          => '27222222223',
            'participacion' => '50',
            'cargo'         => 'Presidente',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('50.00', $created['json']['data']['participacion']);

        $this->assertCount(1, $this->getJson("/empresas/{$e}/socios", $auth)['json']['data']);

        $upd = $this->putJson("/empresas/{$e}/socios/{$id}", ['nombre' => 'María G.', 'participacion' => '60'], $auth);
        $this->assertSame(200, $upd['status']);
        $this->assertSame('60.00', $upd['json']['data']['participacion']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/socios/{$id}", $auth)['status']);
    }

    public function test_socios_aislamiento_por_tenant(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'ACME'], $auth)['json']['data']['id'];
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/socios", $bobAuth)['status']);
    }
}
