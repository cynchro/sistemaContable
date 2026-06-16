<?php

namespace App\Modules\Honorarios\Services;

use App\Support\DB;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Honorarios\Calc\HonorarioCalculator;
use App\Modules\Honorarios\Repositories\FactorComplejidadRepository;
use App\Modules\Honorarios\Repositories\HonorarioRepository;
use App\Modules\Honorarios\Repositories\ServicioRepository;

/**
 * Documento de honorarios por empresa (contribuyente). Resuelve la UC de cada
 * servicio y el factor de la complejidad, calcula los importes con HonorarioCalculator
 * y persiste cabecera + líneas en una transacción.
 */
class HonorarioService
{
    public function __construct(
        private HonorarioRepository $honorarios,
        private ServicioRepository $servicios,
        private FactorComplejidadRepository $factores,
        private EmpresaRepository $empresas,
        private HonorarioCalculator $calculator,
        private DB $db,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->honorarios->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->honorarios->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data  fecha, valor_uc, descripcion, lineas[]
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        $valorUc = is_numeric($data['valor_uc'] ?? null) ? (string) $data['valor_uc'] : '0';
        $lineas  = $this->resolverLineas($data['lineas'] ?? [], $tenantId);

        $calc = $this->calculator->calcular($valorUc, $lineas);

        $header = [
            'fecha'       => $data['fecha'] ?? null,
            'valor_uc'    => $valorUc,
            'descripcion' => $data['descripcion'] ?? null,
            'estado'      => 'borrador',
            'total'       => $calc['total'],
        ];

        return $this->db->withTransaction(
            fn () => $this->honorarios->create($header, $calc['lineas'], $empresaId)
        );
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->honorarios->findById($id, $empresaId);
        $this->honorarios->delete($id, $empresaId);
    }

    /**
     * Resuelve cada línea: trae la UC del servicio y el factor de la complejidad.
     *
     * @param  mixed  $lineas
     * @return list<array<string, mixed>>
     */
    private function resolverLineas(mixed $lineas, string $tenantId): array
    {
        if (!is_array($lineas)) {
            throw new ValidationException(['lineas' => ['lineas debe ser una lista.']]);
        }

        $out = [];
        foreach (array_values($lineas) as $i => $linea) {
            if (!is_array($linea) || !isset($linea['servicio_id']) || !is_numeric($linea['servicio_id'])) {
                throw new ValidationException(['lineas' => ["La línea {$i} requiere servicio_id."]]);
            }

            $servicio    = $this->servicios->findById((int) $linea['servicio_id'], $tenantId);
            $complejidad = isset($linea['complejidad']) ? (string) $linea['complejidad'] : null;
            $factor      = $complejidad !== null ? $this->factores->findByNivel($complejidad, $tenantId) : null;

            $out[] = [
                'servicio_id' => (int) $servicio['id'],
                'codigo'      => $servicio['codigo'] ?? null,
                'descripcion' => $servicio['descripcion'] ?? null,
                'uc'          => $servicio['uc'] ?? 0,
                'complejidad' => $complejidad,
                'factor'      => $factor['factor'] ?? 1,
                'cantidad'    => is_numeric($linea['cantidad'] ?? null) ? $linea['cantidad'] : 1,
            ];
        }

        return $out;
    }
}
