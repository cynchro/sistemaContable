<?php

namespace App\Modules\Iva\Services;

use App\Support\DB;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Repositories\VentaRepository;

/**
 * Orquesta el alta/edición/baja de comprobantes de venta (agregado cabecera +
 * discriminación + retenciones). Reglas (ingeniería inversa del legacy):
 *  - la empresa debe pertenecer al tenant y el período a la empresa;
 *  - no se cargan ni modifican comprobantes en un período cerrado;
 *  - la fecha del comprobante debe caer dentro del rango del período;
 *  - el IVA y el total los calcula IvaComprobanteCalculator (motor de cálculos);
 *  - todo el agregado se persiste en una sola transacción.
 */
class VentaService
{
    public function __construct(
        private VentaRepository $ventas,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private IvaComprobanteCalculator $calculator,
        private DB $db,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->ventas->findAllByPeriodo($periodoId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->ventas->findById($id, $periodoId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, int $periodoId, string $tenantId): array
    {
        $periodo = $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->assertFechaEnPeriodo($data['fecha'] ?? null, $periodo);

        [$header, $lineas] = $this->preparar($data);

        return $this->db->withTransaction(
            fn () => $this->ventas->create($header, $lineas, $periodoId)
        );
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, int $periodoId, string $tenantId): array
    {
        $periodo = $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->ventas->findById($id, $periodoId);
        $this->assertFechaEnPeriodo($data['fecha'] ?? null, $periodo);

        [$header, $lineas] = $this->preparar($data);

        return $this->db->withTransaction(
            fn () => $this->ventas->replace($id, $header, $lineas, $periodoId)
        );
    }

    public function delete(int $id, int $empresaId, int $periodoId, string $tenantId): void
    {
        $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->ventas->findById($id, $periodoId);

        $this->db->withTransaction(fn () => $this->ventas->delete($id, $periodoId));
    }

    /**
     * Corre la calculadora y arma [cabecera con total, líneas con importes].
     *
     * @param  array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function preparar(array $data): array
    {
        $lineasInput = $this->normalizarDiscriminaciones($data['discriminaciones'] ?? []);
        $calc        = $this->calculator->calcular($data, $lineasInput);

        $header = $data;
        $header['total'] = $calc['total'];

        $lineas = [];
        foreach ($lineasInput as $i => $linea) {
            $lineas[] = [
                'neto_gravado'     => $calc['lineas'][$i]['neto_gravado'],
                'iva_alicuota'     => $linea['iva_alicuota'],
                'iva_importe'      => $calc['lineas'][$i]['iva_importe'],
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'],
                'iva_inc_importe'  => $calc['lineas'][$i]['iva_inc_importe'],
                'reintegro_t'      => $linea['reintegro_t'],
                'concepto'         => $linea['concepto'],
                'retenciones'      => $linea['retenciones'],
            ];
        }

        return [$header, $lineas];
    }

    /**
     * Valida y normaliza las líneas de discriminación (y sus retenciones).
     *
     * @param  mixed $discriminaciones
     * @return list<array<string, mixed>>
     */
    private function normalizarDiscriminaciones(mixed $discriminaciones): array
    {
        if (!is_array($discriminaciones)) {
            throw new ValidationException(['discriminaciones' => ['discriminaciones debe ser una lista.']]);
        }

        $out = [];
        foreach (array_values($discriminaciones) as $i => $linea) {
            if (
                !is_array($linea)
                || !$this->esNumerico($linea['neto_gravado'] ?? null)
                || !$this->esNumerico($linea['iva_alicuota'] ?? null)
            ) {
                throw new ValidationException([
                    'discriminaciones' => ["La línea {$i} requiere neto_gravado e iva_alicuota numéricos."],
                ]);
            }

            $retenciones = [];
            foreach (array_values((array) ($linea['retenciones'] ?? [])) as $j => $ret) {
                if (!is_array($ret) || !$this->esNumerico($ret['importe'] ?? null)) {
                    throw new ValidationException([
                        'discriminaciones' => ["La retención {$j} de la línea {$i} requiere importe numérico."],
                    ]);
                }
                $retenciones[] = [
                    'tipo_retencion_id' => $ret['tipo_retencion_id'] ?? null,
                    'porcentaje'        => $ret['porcentaje'] ?? null,
                    'importe'           => $ret['importe'],
                ];
            }

            $out[] = [
                'neto_gravado'     => $linea['neto_gravado'],
                'iva_alicuota'     => $linea['iva_alicuota'],
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'] ?? null,
                'reintegro_t'      => $linea['reintegro_t'] ?? null,
                'concepto'         => $linea['concepto'] ?? null,
                'retenciones'      => $retenciones,
            ];
        }

        return $out;
    }

    private function esNumerico(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    /** @return array<string, mixed> período validado */
    private function assertPeriodo(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->periodos->findById($periodoId, $empresaId);
    }

    /** @return array<string, mixed> período validado y abierto */
    private function assertPeriodoEditable(int $empresaId, int $periodoId, string $tenantId): array
    {
        $periodo = $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        if (($periodo['cerrado'] ?? 'N') === 'S') {
            throw new ConflictException('No se pueden cargar o modificar comprobantes en un período cerrado.');
        }

        return $periodo;
    }

    /** @param array<string, mixed> $periodo */
    private function assertFechaEnPeriodo(?string $fecha, array $periodo): void
    {
        if ($fecha === null) {
            return;
        }

        $ini = $periodo['fecha_ini'] ?? null;
        $fin = $periodo['fecha_fin'] ?? null;

        if (($ini !== null && $fecha < $ini) || ($fin !== null && $fecha > $fin)) {
            throw new ValidationException([
                'fecha' => ['La fecha del comprobante está fuera del rango del período.'],
            ]);
        }
    }
}
