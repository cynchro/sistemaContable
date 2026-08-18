<?php

namespace Tests\Feature;

use App\Modules\Compartido\Sige\ContribuyenteSige;
use App\Modules\Compartido\Sige\SigeClient;

/**
 * Endpoint GET /sige/{cuit}/sugerencia. Sustituye el SigeClient real por un doble (sin red
 * ni SIGE levantado) para probar la capa HTTP + validación + mapeo de la respuesta. El caso
 * "SIGE caído" (SigeException) se prueba a nivel unitario en SigeServiceTest — el harness de
 * FeatureTestCase solo traduce a JSON las AppException (ver FeatureTestCase::request), igual
 * que el resto de los tests de integraciones externas (AFIP) de este repo.
 */
class SigeSugerenciaTest extends FeatureTestCase
{
    private function fakeSige(?ContribuyenteSige $resultado): SigeClient
    {
        return new class ($resultado) implements SigeClient {
            public function __construct(private ?ContribuyenteSige $resultado)
            {
            }

            public function buscarPorCuit(string $cuit): ?ContribuyenteSige
            {
                return $this->resultado;
            }
        };
    }

    public function test_sugerencia_mapea_los_campos_del_alta_de_empresa(): void
    {
        $contribuyente = new ContribuyenteSige(
            personaId: 5,
            cuit: '20374625323',
            nombre: 'BLANCO SERGIO DANIEL',
            tipoPersona: 'fisica',
            contacto: 'Sergio Blanco',
            telefono: '351-1234567',
            email: 'sergio@example.com',
            inscripcion: 'RI',
            contabilidad: 'Mensual',
        );
        $this->app->instance(SigeClient::class, $this->fakeSige($contribuyente));
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/sige/20374625323/sugerencia', $auth);

        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertTrue($d['encontrado']);
        $this->assertSame(5, $d['sige_persona_id']);
        $this->assertSame('BLANCO SERGIO DANIEL', $d['nombre']);
        $this->assertSame('sergio@example.com', $d['email']);
        $this->assertSame('Sergio Blanco', $d['contacto']);
        $this->assertSame('RI', $d['inscripcion']);
        $this->assertSame('Mensual', $d['contabilidad']);
    }

    public function test_cuit_inexistente_en_sige_devuelve_encontrado_false_sin_error(): void
    {
        $this->app->instance(SigeClient::class, $this->fakeSige(null));
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/sige/20999999999/sugerencia', $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertFalse($resp['json']['data']['encontrado']);
        $this->assertSame('20999999999', $resp['json']['data']['cuit']);
    }

    public function test_cuit_invalido_devuelve_422(): void
    {
        $this->app->instance(SigeClient::class, $this->fakeSige(null));
        $auth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson('/sige/123/sugerencia', $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuit', $resp['json']['errors']);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->app->instance(SigeClient::class, $this->fakeSige(null));

        $resp = $this->getJson('/sige/20374625323/sugerencia');

        $this->assertSame(401, $resp['status']);
    }
}
