<?php

namespace App\Modules\Sueldos\Services;

use App\Support\DB;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Calc\ContribucionCalculator;
use App\Modules\Sueldos\Repositories\ContribucionRepository;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Repositories\LiquidacionRepository;

/**
 * Contribuciones patronales: ABM de definiciones por empresa y cálculo/persistencia
 * de las contribuciones liquidadas por empleado (sobre la base del recibo).
 */
class ContribucionService
{
    public function __construct(
        private ContribucionRepository $contribuciones,
        private LiquidacionRepository $liquidaciones,
        private EmpleadoRepository $empleados,
        private EmpresaRepository $empresas,
        private ContribucionCalculator $calculator,
        private DB $db,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->contribuciones->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->contribuciones->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->contribuciones->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->contribuciones->findById($id, $empresaId);
        $this->contribuciones->update($id, $data, $empresaId);

        return $this->contribuciones->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->contribuciones->findById($id, $empresaId);
        $this->contribuciones->delete($id, $empresaId);
    }

    /**
     * Calcula y persiste las contribuciones del empleado en la liquidación.
     *
     * @return array{lineas: list<array<string, mixed>>, total: string}
     */
    public function calcular(int $liqId, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertContexto($liqId, $empleadoId, $empresaId, $tenantId);

        $resultado = $this->calculator->calcular(
            $this->contribuciones->baseImponible($liqId, $empleadoId),
            $this->contribuciones->findAllByEmpresa($empresaId),
            $this->contribuciones->detraccionMonto($empresaId),
        );

        $this->db->withTransaction(
            fn () => $this->contribuciones->replaceLiquidadas($liqId, $empleadoId, $resultado['lineas'])
        );

        return $resultado;
    }

    /** @return array<string, mixed> */
    public function liquidadas(int $liqId, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertContexto($liqId, $empleadoId, $empresaId, $tenantId);

        return ['lineas' => $this->contribuciones->liquidadas($liqId, $empleadoId)];
    }

    private function assertContexto(int $liqId, int $empleadoId, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->liquidaciones->findById($liqId, $empresaId);
        $this->empleados->findById($empleadoId, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }
}
