<?php

namespace App\Modules\Iva\Services;

use App\Support\DB;
use App\Support\ReferenceValidator;
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
        private ReferenceValidator $refs,
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
        $this->assertReferencias($data, $empresaId, $tenantId);
        $this->assertNoDuplicado($data, $empresaId);

        [$header, $lineas, $asociados] = $this->preparar($data);

        return $this->db->withTransaction(
            fn () => $this->ventas->create($header, $lineas, $asociados, $periodoId)
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
        $this->assertReferencias($data, $empresaId, $tenantId);
        $this->assertNoDuplicado($data, $empresaId, $id);

        [$header, $lineas, $asociados] = $this->preparar($data);

        return $this->db->withTransaction(
            fn () => $this->ventas->replace($id, $header, $lineas, $asociados, $periodoId)
        );
    }

    public function delete(int $id, int $empresaId, int $periodoId, string $tenantId): void
    {
        $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->ventas->findById($id, $periodoId);

        $this->db->withTransaction(fn () => $this->ventas->delete($id, $periodoId));
    }

    /**
     * Mueve un comprobante a otro período de la misma empresa: ambos períodos deben estar
     * abiertos y la fecha del comprobante debe caer en el rango del destino.
     *
     * @return array<string, mixed>
     */
    public function mover(int $id, int $empresaId, int $periodoId, int $periodoDestinoId, string $tenantId): array
    {
        $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $venta = $this->ventas->findById($id, $periodoId);

        $destino = $this->assertPeriodoEditable($empresaId, $periodoDestinoId, $tenantId);
        $this->assertFechaEnPeriodo($venta['fecha'] ?? null, $destino);

        $this->db->withTransaction(
            fn () => $this->ventas->moverAPeriodo($id, $periodoId, $periodoDestinoId)
        );

        return $this->ventas->findById($id, $periodoDestinoId);
    }

    /**
     * Corre la calculadora y arma [cabecera con total, líneas con importes, asociados].
     *
     * @param  array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
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

        return [$header, $lineas, $this->normalizarAsociados($data['comprobantes_asociados'] ?? [])];
    }

    /**
     * Normaliza los comprobantes asociados (CbtesAsoc para NC/ND).
     *
     * @param  mixed $asociados
     * @return list<array<string, mixed>>
     */
    private function normalizarAsociados(mixed $asociados): array
    {
        if (!is_array($asociados)) {
            throw new ValidationException(['comprobantes_asociados' => ['Debe ser una lista.']]);
        }

        $out = [];
        foreach (array_values($asociados) as $i => $asoc) {
            if (!is_array($asoc) || ($asoc['numero'] ?? '') === '' || ($asoc['punto_venta'] ?? '') === '') {
                throw new ValidationException([
                    'comprobantes_asociados' => ["El asociado {$i} requiere punto_venta y numero."],
                ]);
            }

            $out[] = [
                'tipo_comprobante_id' => $asoc['tipo_comprobante_id'] ?? null,
                'letra'               => $asoc['letra'] ?? null,
                'punto_venta'         => $asoc['punto_venta'],
                'numero'              => $asoc['numero'],
                'cuit'                => $asoc['cuit'] ?? null,
                'fecha'               => $asoc['fecha'] ?? null,
            ];
        }

        return $out;
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

    /**
     * Valida que las FKs de la venta existan y pertenezcan al ámbito: rubro del tenant,
     * cliente de la empresa; el resto son catálogos globales. Devuelve 422 (no 500 por FK).
     *
     * @param array<string, mixed> $data
     */
    private function assertReferencias(array $data, int $empresaId, string $tenantId): void
    {
        $this->refs->validate([
            'tipo_comprobante_id'     => [
                'table' => 'tipos_comprobante', 'value' => $data['tipo_comprobante_id'] ?? null,
            ],
            'tipo_documento_id'       => ['table' => 'tipos_documento', 'value' => $data['tipo_documento_id'] ?? null],
            'condicion_iva_id'        => ['table' => 'condiciones_iva', 'value' => $data['condicion_iva_id'] ?? null],
            'provincia_id'            => ['table' => 'provincias', 'value' => $data['provincia_id'] ?? null],
            'tipo_operacion_venta_id' => [
                'table' => 'tipos_operacion_venta', 'value' => $data['tipo_operacion_venta_id'] ?? null,
            ],
            'tipo_moneda_id'          => ['table' => 'tipos_moneda', 'value' => $data['tipo_moneda_id'] ?? null],
            'rubro_id'                => [
                'table' => 'rubros', 'value' => $data['rubro_id'] ?? null, 'scope' => ['tenant_id' => $tenantId],
            ],
            'cliente_id'              => [
                'table' => 'iva_clientes', 'value' => $data['cliente_id'] ?? null,
                'scope' => ['empresa_id' => $empresaId],
            ],
        ]);
    }

    /**
     * Evita cargar dos veces el mismo comprobante en la empresa (tipo + letra + punto de
     * venta + número). Se omite si falta punto de venta o número (no hay clave para comparar).
     *
     * @param array<string, mixed> $data
     */
    private function assertNoDuplicado(array $data, int $empresaId, int $exceptId = 0): void
    {
        $pv     = trim((string) ($data['punto_venta'] ?? ''));
        $numero = trim((string) ($data['numero'] ?? ''));

        if ($pv === '' || $numero === '') {
            return;
        }

        $dupId = $this->ventas->findDuplicado(
            $empresaId,
            isset($data['tipo_comprobante_id']) ? (int) $data['tipo_comprobante_id'] : null,
            $data['letra'] ?? null,
            $pv,
            $numero,
            $exceptId,
        );

        if ($dupId !== null) {
            throw new ConflictException("Ya existe ese comprobante (punto de venta {$pv}, número {$numero}).");
        }
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
