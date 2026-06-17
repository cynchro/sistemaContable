<?php

namespace Tests\Feature;

/**
 * Cálculo del SAC (aguinaldo): mejor remuneración remunerativa del semestre × 50%
 * (proporcional por días). Toma la base del historial de liquidaciones.
 */
class SacTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, e:int, emp:int} */
    private function escenario(): array
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'Sueldos SA'], $auth)['json']['data']['id'];
        $emp = (int) $this->postJson("/empresas/{$e}/empleados", ['nombres' => 'Juan'], $auth)['json']['data']['id'];

        // Dos liquidaciones mensuales con remunerativo 80.000 y 100.000; una línea no rem (excluida).
        $this->pdo->exec(
            "INSERT INTO liquidaciones (id, empresa_id, periodo_liquidado, tipo)
             VALUES (5001, {$e}, '2026-01', 1), (5002, {$e}, '2026-02', 1)"
        );
        $this->pdo->exec(
            "INSERT INTO recibos (liquidacion_id, empleado_id, item, importe, tipo) VALUES
                (5001, {$emp}, 1, 80000, 1),
                (5002, {$emp}, 1, 100000, 1),
                (5002, {$emp}, 2, 50000, 2)"
        );

        return ['auth' => $auth, 'e' => $e, 'emp' => $emp];
    }

    public function test_sac_toma_la_mejor_remuneracion_del_semestre(): void
    {
        ['auth' => $auth, 'e' => $e, 'emp' => $emp] = $this->escenario();

        $resp = $this->getJson("/empresas/{$e}/empleados/{$emp}/sac?desde=2026-01&hasta=2026-06", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('100000.00', $resp['json']['data']['mejor_remuneracion']);
        $this->assertSame('50000.00', $resp['json']['data']['sac']);
    }

    public function test_sac_proporcional_por_dias(): void
    {
        ['auth' => $auth, 'e' => $e, 'emp' => $emp] = $this->escenario();

        $resp = $this->getJson(
            "/empresas/{$e}/empleados/{$emp}/sac?desde=2026-01&hasta=2026-06&dias_trabajados=90",
            $auth,
        );

        $this->assertSame(200, $resp['status']);
        $this->assertSame('25000.00', $resp['json']['data']['sac']);
    }

    public function test_periodo_invalido_da_422(): void
    {
        ['auth' => $auth, 'e' => $e, 'emp' => $emp] = $this->escenario();

        $resp = $this->getJson("/empresas/{$e}/empleados/{$emp}/sac?desde=2026&hasta=2026-06", $auth);

        $this->assertSame(422, $resp['status']);
    }
}
