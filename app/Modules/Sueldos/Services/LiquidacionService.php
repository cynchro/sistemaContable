<?php

namespace App\Modules\Sueldos\Services;

use App\Support\DB;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Calc\AntiguedadCalculator;
use App\Modules\Sueldos\Calc\LiquidacionCalculator;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Repositories\LiquidacionRepository;

/**
 * Orquesta liquidaciones de sueldos: cabecera (corrida), novedades por empleado y
 * la ejecución que arma el contexto (BASICO del legajo/categoría, ANTIG por
 * antigüedad), corre el LiquidacionCalculator y persiste los recibos en transacción.
 *
 * Reglas: la empresa debe pertenecer al tenant; una liquidación bloqueada no se
 * edita ni recalcula.
 */
class LiquidacionService
{
    public function __construct(
        private LiquidacionRepository $liquidaciones,
        private EmpleadoRepository $empleados,
        private EmpresaRepository $empresas,
        private AntiguedadCalculator $antiguedad,
        private LiquidacionCalculator $calculator,
        private DB $db,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->liquidaciones->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->liquidaciones->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function crear(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->liquidaciones->create($data, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $liq = $this->liquidaciones->findById($id, $empresaId);
        $this->assertNoBloqueada($liq);

        $this->liquidaciones->delete($id, $empresaId);
    }

    /** @return list<array<string, mixed>> */
    public function getNovedades(int $liqId, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertContexto($liqId, $empleadoId, $empresaId, $tenantId);

        return $this->liquidaciones->novedades($liqId, $empleadoId);
    }

    /**
     * @param  array<int, mixed> $items  cada uno con concepto_id (+ cantidad/importe)
     * @return list<array<string, mixed>>
     */
    public function setNovedades(int $liqId, int $empleadoId, array $items, int $empresaId, string $tenantId): array
    {
        [, $liq] = $this->assertContexto($liqId, $empleadoId, $empresaId, $tenantId);
        $this->assertNoBloqueada($liq);

        $normalizadas = [];
        foreach ($items as $i => $item) {
            if (!is_array($item) || !isset($item['concepto_id']) || !is_numeric($item['concepto_id'])) {
                throw new ValidationException(['novedades' => ["El ítem {$i} requiere concepto_id numérico."]]);
            }
            $normalizadas[] = [
                'concepto_id' => (int) $item['concepto_id'],
                'cantidad'    => is_numeric($item['cantidad'] ?? null) ? $item['cantidad'] : 0,
                'importe'     => is_numeric($item['importe'] ?? null) ? $item['importe'] : 0,
            ];
        }

        $this->db->withTransaction(fn () => $this->liquidaciones->replaceNovedades($liqId, $empleadoId, $normalizadas));

        return $this->liquidaciones->novedades($liqId, $empleadoId);
    }

    /**
     * Ejecuta la liquidación del empleado: calcula y persiste los recibos.
     *
     * @return array<string, mixed>
     */
    public function liquidar(int $liqId, int $empleadoId, int $empresaId, string $tenantId): array
    {
        [$empleado, $liq] = $this->assertContexto($liqId, $empleadoId, $empresaId, $tenantId);
        $this->assertNoBloqueada($liq);

        $basico = (string) ($empleado['basico'] ?? '0');
        if ((float) $basico === 0.0 && !empty($empleado['categoria_id'])) {
            $basico = $this->liquidaciones->valorCategoria((int) $empleado['categoria_id']) ?? '0';
        }

        $antig = $this->antiguedad->aniosCumplidos(
            isset($empleado['fecha_ingreso']) ? (string) $empleado['fecha_ingreso'] : null,
            isset($liq['fecha_hasta']) ? (string) $liq['fecha_hasta'] : null,
        );

        $novedades = [];
        foreach ($this->liquidaciones->novedades($liqId, $empleadoId) as $nov) {
            $novedades[(int) $nov['concepto_id']] = [
                'cantidad' => $nov['cantidad'],
                'importe'  => $nov['importe'],
            ];
        }

        $resultado = $this->calculator->liquidar(
            ['basico' => $basico, 'antiguedad' => $antig],
            $this->liquidaciones->conceptosDeEmpresa($empresaId),
            $novedades,
        );

        $this->db->withTransaction(
            fn () => $this->liquidaciones->replaceRecibos($liqId, $empleadoId, $resultado['lineas'])
        );

        return $resultado;
    }

    /** @return array<string, mixed> recibo (líneas + totales) */
    public function recibo(int $liqId, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertContexto($liqId, $empleadoId, $empresaId, $tenantId);

        return ['lineas' => $this->liquidaciones->recibos($liqId, $empleadoId)];
    }

    /**
     * Valida empresa∈tenant, liquidación∈empresa y empleado∈empresa.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [empleado, liquidacion]
     */
    private function assertContexto(int $liqId, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $liq      = $this->liquidaciones->findById($liqId, $empresaId);
        $empleado = $this->empleados->findById($empleadoId, $empresaId);

        return [$empleado, $liq];
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }

    /** @param array<string, mixed> $liq */
    private function assertNoBloqueada(array $liq): void
    {
        if (($liq['bloqueada'] ?? 'N') === 'S') {
            throw new ConflictException('La liquidación está bloqueada.');
        }
    }
}
