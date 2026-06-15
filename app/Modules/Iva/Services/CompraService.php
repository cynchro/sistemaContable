<?php

namespace App\Modules\Iva\Services;

use App\Support\DB;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Repositories\CompraRepository;

/**
 * Orquesta el alta/edición/baja de comprobantes de compra (agregado cabecera +
 * discriminación + retenciones). Mismas reglas que ventas (empresa∈tenant,
 * período abierto, fecha en rango, cálculo por el motor, todo en una transacción).
 * Diferencia: cada línea lleva `cf_computable` (crédito fiscal computable); si no
 * se informa, se asume igual al IVA calculado (crédito íntegramente computable).
 */
class CompraService
{
    public function __construct(
        private CompraRepository $compras,
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

        return $this->compras->findAllByPeriodo($periodoId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->compras->findById($id, $periodoId);
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
            fn () => $this->compras->create($header, $lineas, $periodoId)
        );
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, int $periodoId, string $tenantId): array
    {
        $periodo = $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->compras->findById($id, $periodoId);
        $this->assertFechaEnPeriodo($data['fecha'] ?? null, $periodo);

        [$header, $lineas] = $this->preparar($data);

        return $this->db->withTransaction(
            fn () => $this->compras->replace($id, $header, $lineas, $periodoId)
        );
    }

    public function delete(int $id, int $empresaId, int $periodoId, string $tenantId): void
    {
        $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->compras->findById($id, $periodoId);

        $this->db->withTransaction(fn () => $this->compras->delete($id, $periodoId));
    }

    /**
     * Corre la calculadora y arma [cabecera con total, líneas con importes + cf_computable].
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
            $ivaImporte = $calc['lineas'][$i]['iva_importe'];
            $lineas[] = [
                'neto_gravado'     => $calc['lineas'][$i]['neto_gravado'],
                'iva_alicuota'     => $linea['iva_alicuota'],
                'iva_importe'      => $ivaImporte,
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'],
                'iva_inc_importe'  => $calc['lineas'][$i]['iva_inc_importe'],
                // Crédito fiscal computable: informado, o el IVA completo por defecto.
                'cf_computable'    => $linea['cf_computable'] ?? $ivaImporte,
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

            $cf = $linea['cf_computable'] ?? null;
            if ($cf !== null && !$this->esNumerico($cf)) {
                throw new ValidationException([
                    'discriminaciones' => ["La línea {$i} tiene cf_computable no numérico."],
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
                'cf_computable'    => $cf,
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
