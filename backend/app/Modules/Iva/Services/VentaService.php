<?php

namespace App\Modules\Iva\Services;

use App\Support\DB;
use App\Support\ReferenceValidator;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Compartido\Repositories\TipoRetencionRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Calc\PercepcionCalculator;
use App\Modules\Iva\Repositories\VentaRepository;

/**
 * Orquesta el alta/edición/baja de comprobantes de venta (agregado cabecera +
 * discriminación + percepciones). Reglas (ingeniería inversa del legacy):
 *  - la empresa debe pertenecer al tenant y el período a la empresa;
 *  - no se cargan ni modifican comprobantes en un período cerrado;
 *  - la fecha del comprobante debe caer dentro del rango del período;
 *  - el IVA y el total los calcula IvaComprobanteCalculator (motor de cálculos);
 *  - las percepciones (a nivel comprobante) integran el total (respuestas.md A1) y se
 *    resuelven con PercepcionCalculator según la base parametrizada en el tipo (A2);
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
        private PercepcionCalculator $percepcionCalc,
        private TipoRetencionRepository $tiposRetencion,
    ) {
    }

    /**
     * Listado paginado y filtrado de comprobantes del período.
     *
     * @param  array{fecha_desde?: ?string, fecha_hasta?: ?string, cliente_id?: ?int, letra?: ?string} $filtros
     * @return array<string, mixed>
     */
    public function list(
        int $empresaId,
        int $periodoId,
        string $tenantId,
        array $filtros = [],
        int $page = 1,
        int $perPage = 50,
    ): array {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->ventas->findPaginado(
            $periodoId,
            $filtros,
            max(1, $page),
            min(max(1, $perPage), 200),
        );
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

        [$header, $lineas, $percepciones, $asociados] = $this->preparar($data, $tenantId);

        return $this->db->withTransaction(
            fn () => $this->ventas->create($header, $lineas, $percepciones, $asociados, $periodoId)
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

        [$header, $lineas, $percepciones, $asociados] = $this->preparar($data, $tenantId);

        return $this->db->withTransaction(
            fn () => $this->ventas->replace($id, $header, $lineas, $percepciones, $asociados, $periodoId)
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
     * Corre la calculadora y arma [cabecera con total, líneas, percepciones, asociados].
     *
     * @param  array<string, mixed> $data
     * @return array{
     *   0: array<string, mixed>, 1: list<array<string, mixed>>,
     *   2: list<array<string, mixed>>, 3: list<array<string, mixed>>
     * }
     */
    private function preparar(array $data, string $tenantId): array
    {
        $lineasInput  = $this->normalizarDiscriminaciones($data['discriminaciones'] ?? []);
        $percepciones = $this->resolverPercepciones(
            $this->normalizarPercepciones($data['percepciones'] ?? []),
            $data,
            $lineasInput,
            $tenantId,
        );
        $calc = $this->calculator->calcular($data, $lineasInput, $percepciones);

        $header = $data;
        $header['total'] = $calc['total'];

        $lineas = [];
        foreach ($lineasInput as $i => $linea) {
            $lineas[] = [
                'neto_gravado'     => $calc['lineas'][$i]['neto_gravado'],
                'cuenta_id'        => $linea['cuenta_id'],
                'iva_alicuota'     => $linea['iva_alicuota'],
                'iva_importe'      => $calc['lineas'][$i]['iva_importe'],
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'],
                'iva_inc_importe'  => $calc['lineas'][$i]['iva_inc_importe'],
                'reintegro_t'      => $linea['reintegro_t'],
                'concepto'         => $linea['concepto'],
            ];
        }

        return [$header, $lineas, $percepciones, $this->normalizarAsociados($data['comprobantes_asociados'] ?? [])];
    }

    /**
     * Resuelve cada percepción del comprobante: toma del tipo de retención la base de
     * cálculo (parametrizada) y la alícuota por defecto, y delega en PercepcionCalculator
     * el cálculo de base e importe (que integran el total). Un importe/base/alícuota
     * informados pisan los del tipo.
     *
     * @param  list<array<string, mixed>> $percepciones normalizadas
     * @param  array<string, mixed>       $cabecera
     * @param  list<array<string, mixed>> $lineas
     * @return list<array<string, mixed>>
     */
    private function resolverPercepciones(array $percepciones, array $cabecera, array $lineas, string $tenantId): array
    {
        $out = [];
        foreach ($percepciones as $perc) {
            $tipo = $this->tiposRetencion->findVisible($perc['tipo_retencion_id'], $tenantId);
            $calc = $this->percepcionCalc->calcular($cabecera, $lineas, [
                'base_calculo' => (string) ($tipo['base_calculo'] ?? 'neto_gravado'),
                'alicuota'     => $perc['alicuota'] ?? ($tipo['alicuota'] ?? null),
                'base'         => $perc['base'],
                'importe'      => $perc['importe'],
            ]);

            $out[] = [
                'tipo_retencion_id' => $perc['tipo_retencion_id'],
                'provincia_id'      => $perc['provincia_id'] ?? ($tipo['provincia_id'] ?? null),
                'base'              => $calc['base'],
                'alicuota'          => $calc['alicuota'],
                'importe'           => $calc['importe'],
            ];
        }

        return $out;
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
     * Valida y normaliza las líneas de discriminación.
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

            $out[] = [
                'neto_gravado'     => $linea['neto_gravado'],
                'cuenta_id'        => $this->normalizarCuentaId($linea['cuenta_id'] ?? null),
                'iva_alicuota'     => $linea['iva_alicuota'],
                // Override opcional del importe de IVA (regla del asterisco): sólo si es numérico.
                'iva_importe'      => $this->esNumerico($linea['iva_importe'] ?? null) ? $linea['iva_importe'] : null,
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'] ?? null,
                'reintegro_t'      => $linea['reintegro_t'] ?? null,
                'concepto'         => $linea['concepto'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Valida y normaliza las percepciones del comprobante (a nivel cabecera). Cada una
     * requiere `tipo_retencion_id`; `alicuota`, `base`, `importe` y `provincia_id` son
     * opcionales (si faltan, se toman del tipo o se calculan según su base).
     *
     * @param  mixed $percepciones
     * @return list<array<string, mixed>>
     */
    private function normalizarPercepciones(mixed $percepciones): array
    {
        if (!is_array($percepciones)) {
            throw new ValidationException(['percepciones' => ['percepciones debe ser una lista.']]);
        }

        $out = [];
        foreach (array_values($percepciones) as $i => $perc) {
            if (!is_array($perc) || !$this->esNumerico($perc['tipo_retencion_id'] ?? null)) {
                throw new ValidationException([
                    'percepciones' => ["La percepción {$i} requiere tipo_retencion_id."],
                ]);
            }

            $out[] = [
                'tipo_retencion_id' => (int) $perc['tipo_retencion_id'],
                'alicuota'          => $this->esNumerico($perc['alicuota'] ?? null) ? $perc['alicuota'] : null,
                'base'              => $this->esNumerico($perc['base'] ?? null) ? $perc['base'] : null,
                'importe'           => $this->esNumerico($perc['importe'] ?? null) ? $perc['importe'] : null,
                'provincia_id'      => $this->esNumerico($perc['provincia_id'] ?? null)
                    ? (int) $perc['provincia_id'] : null,
            ];
        }

        return $out;
    }

    private function esNumerico(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    /** Cuenta de mayorización de la línea: entero positivo o null (sin imputar). */
    private function normalizarCuentaId(mixed $value): ?int
    {
        return $this->esNumerico($value) && (int) $value > 0 ? (int) $value : null;
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
            'cuenta_debe_id'          => [
                'table' => 'cuentas', 'value' => $data['cuenta_debe_id'] ?? null,
                'scope' => ['empresa_id' => $empresaId],
            ],
            'cuenta_haber_id'         => [
                'table' => 'cuentas', 'value' => $data['cuenta_haber_id'] ?? null,
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
