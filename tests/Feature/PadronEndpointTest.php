<?php

namespace Tests\Feature;

use App\Modules\Iva\Afip\Padron\PadronClient;
use App\Modules\Iva\Afip\Padron\PersonaPadron;

/**
 * Endpoint GET /padron/{cuit}. Sustituye el PadronClient real por un doble (sin red ni
 * certificado AFIP) para probar la capa HTTP + validación + mapeo de la respuesta.
 */
class PadronEndpointTest extends FeatureTestCase
{
    private function fakePadron(): PadronClient
    {
        return new class implements PadronClient {
            public function consultar(string $cuit): PersonaPadron
            {
                return new PersonaPadron(
                    cuit: $cuit,
                    tipoPersona: 'JURIDICA',
                    estadoClave: 'ACTIVO',
                    denominacion: 'ACME SA',
                    domicilio: ['direccion' => 'Av. Siempreviva 742', 'provincia' => 'CORDOBA'],
                    impuestos: [30],
                );
            }
        };
    }

    public function test_consulta_padron_por_cuit(): void
    {
        $this->app->instance(PadronClient::class, $this->fakePadron());
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/padron/30711111118', $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('30711111118', $resp['json']['data']['cuit']);
        $this->assertSame('ACME SA', $resp['json']['data']['denominacion']);
        $this->assertSame('JURIDICA', $resp['json']['data']['tipo_persona']);
        $this->assertSame('CORDOBA', $resp['json']['data']['domicilio']['provincia']);
    }

    public function test_sugerencia_mapea_campos_de_alta(): void
    {
        $this->app->instance(PadronClient::class, $this->fakePadron());
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/padron/30711111118/sugerencia', $auth);

        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertSame('ACME SA', $d['nombre']);
        $this->assertSame('30711111118', $d['cuit']);
        $this->assertSame('Av. Siempreviva 742', $d['domicilio']);
        // El bloque crudo de padrón viaja para que el front complete los desplegables.
        $this->assertSame('JURIDICA', $d['padron']['tipo_persona']);
        $this->assertSame([30], $d['padron']['impuestos']);
    }

    public function test_cuit_invalido_devuelve_422(): void
    {
        $this->app->instance(PadronClient::class, $this->fakePadron());
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/padron/123', $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuit', $resp['json']['errors']);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->app->instance(PadronClient::class, $this->fakePadron());

        $resp = $this->getJson('/padron/30711111118');

        $this->assertSame(401, $resp['status']);
    }
}
