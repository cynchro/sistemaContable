<?php

namespace App\Modules\Iva\Services;

use App\Modules\Iva\Calc\LibroIvaCalculator;
use App\Modules\Iva\Calc\AlertaEstadisticaCalculator;
use App\Modules\Iva\Repositories\LibroIvaRepository;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;

/**
 * Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): para cada empresa del
 * tenant, compara el total de ventas/compras de su último período contra el promedio de los
 * anteriores. Calculado al vuelo (mismo criterio que el resto del sistema: "totales de período
 * derivados on-the-fly", sin columnas persistidas ni job de cron) — no hay infraestructura de
 * tareas programadas en este entorno, así que "alerta diaria" del documento se traduce, en esta
 * v1, a una vista que recalcula en cada consulta.
 */
class AlertaEstadisticaService
{
    public function __construct(
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private LibroIvaRepository $libro,
        private LibroIvaCalculator $libroCalculator,
        private AlertaEstadisticaCalculator $alertaCalculator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listar(string $tenantId): array
    {
        $alertas = [];

        foreach ($this->empresas->findAll($tenantId) as $empresa) {
            $empresaId = (int) $empresa['id'];
            $periodos  = $this->periodos->findAllByEmpresa($empresaId);

            if (count($periodos) < AlertaEstadisticaCalculator::MIN_PERIODOS_HISTORIAL + 1) {
                continue;
            }

            $totales = array_map(fn (array $p): array => [
                'periodo' => $p,
                ...$this->totalesDelPeriodo((int) $p['id']),
            ], $periodos);

            // findAllByEmpresa ordena por fecha_ini asc: el último es el más reciente.
            $actual     = array_pop($totales);
            $historicos = $totales;

            foreach (['ventas' => 'total_ventas', 'compras' => 'total_compras'] as $tipo => $campo) {
                $evaluacion = $this->alertaCalculator->evaluar(
                    $actual[$campo],
                    array_column($historicos, $campo),
                );
                if ($evaluacion === null) {
                    continue;
                }
                $alertas[] = [
                    'empresa_id'     => $empresaId,
                    'empresa_nombre' => $empresa['nombre'],
                    'tipo'           => $tipo,
                    'periodo_id'     => (int) $actual['periodo']['id'],
                    'periodo_nombre' => $actual['periodo']['nombre'],
                    'actual'         => $actual[$campo],
                    'promedio'       => $evaluacion['promedio'],
                    'desvio_pct'     => $evaluacion['desvio_pct'],
                    'alerta'         => $evaluacion['alerta'],
                ];
            }
        }

        return $alertas;
    }

    /** @return array{total_ventas: string, total_compras: string} */
    private function totalesDelPeriodo(int $periodoId): array
    {
        $totales = $this->libroCalculator->totalesPeriodo(
            $this->libro->ventasTotalPorSigno($periodoId),
            [],
            $this->libro->comprasTotalPorSigno($periodoId),
            [],
        );

        return [
            'total_ventas'  => $totales['total_ventas'],
            'total_compras' => $totales['total_compras'],
        ];
    }
}
