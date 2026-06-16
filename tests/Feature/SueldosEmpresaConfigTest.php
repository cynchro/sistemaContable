<?php

namespace Tests\Feature;

/**
 * Configuración de sueldos por empresa (1:1): GET vacío inicial, PUT upsert.
 */
class SueldosEmpresaConfigTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Cfg SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    public function test_get_vacio_y_guardar(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $this->assertSame([], $this->getJson("/empresas/{$e}/sueldos/config", $auth)['json']['data']);

        $saved = $this->putJson("/empresas/{$e}/sueldos/config", [
            'nro_anses'     => '123456789',
            'jornada_horas' => '8',
            'tipo_recibo'   => 'A',
        ], $auth);
        $this->assertSame(200, $saved['status']);
        $this->assertSame('123456789', $saved['json']['data']['nro_anses']);

        // Upsert: vuelve a guardar y actualiza (no duplica la fila).
        $again = $this->putJson("/empresas/{$e}/sueldos/config", ['nro_anses' => '999'], $auth);
        $this->assertSame('999', $again['json']['data']['nro_anses']);
        // Persistió la actualización (sigue siendo 1:1).
        $this->assertSame('999', $this->getJson("/empresas/{$e}/sueldos/config", $auth)['json']['data']['nro_anses']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/sueldos/config", $bobAuth)['status']);
    }
}
