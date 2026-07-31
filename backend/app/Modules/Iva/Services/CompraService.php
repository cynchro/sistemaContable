<?php

namespace App\Modules\Iva\Services;

use App\Support\Cuit;
use App\Support\DB;
use App\Support\ReferenceValidator;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Compartido\Repositories\TipoRetencionRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Calc\PercepcionCalculator;
use App\Modules\Iva\Repositories\CompraRepository;
use App\Modules\Iva\Repositories\SujetoRepository;
use App\Modules\Iva\Repositories\SujetoEmpresaRepository;
use App\Modules\Iva\Repositories\ImputacionContableRepository;

/**
 * Orquesta el alta/edición/baja de comprobantes de compra (agregado cabecera +
 * discriminación + percepciones). Mismas reglas que ventas (empresa∈tenant,
 * período abierto, fecha en rango, cálculo por el motor, todo en una transacción).
 * Diferencias: cada línea lleva `cf_computable` (crédito fiscal computable); si no
 * se informa, se asume igual al IVA calculado (crédito íntegramente computable). Las
 * percepciones sufridas se registran a nivel comprobante e integran el total (A1/A2).
 */
class CompraService
{
    public function __construct(
        private CompraRepository $compras,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private IvaComprobanteCalculator $calculator,
        private DB $db,
        private ReferenceValidator $refs,
        private PercepcionCalculator $percepcionCalc,
        private TipoRetencionRepository $tiposRetencion,
        private SujetoEmpresaRepository $sujetoEmpresas,
        private SujetoRepository $sujetos,
        private ImputacionContableRepository $imputacion,
    ) {
    }

    /**
     * Listado paginado y filtrado de comprobantes del período.
     *
     * @param array{
     *   fecha_desde?: ?string, fecha_hasta?: ?string, proveedor_id?: ?int,
     *   cuit?: ?string, letra?: ?string
     * } $filtros
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

        return $this->compras->findPaginado(
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

        return $this->compras->findById($id, $periodoId);
    }

    /** Comprobantes sin proveedor identificado del padrón, para revisión manual. @return list<array<string, mixed>> */
    public function pendientes(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->compras->findPendientes($periodoId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, int $periodoId, string $tenantId): array
    {
        $periodo = $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->assertFechaEnPeriodo($data['fecha'] ?? null, $periodo);
        $data = $this->normalizarImportesOpcionales($this->resolverProveedorPorCuit($data, $tenantId));
        $this->assertReferencias($data, $empresaId, $tenantId);
        $this->assertNoDuplicado($data, $empresaId);

        [$header, $lineas, $percepciones] = $this->preparar($data, $tenantId, $empresaId);
        $this->activarProveedorSiCorresponde($header, $empresaId);

        return $this->db->withTransaction(
            fn () => $this->compras->create($header, $lineas, $percepciones, $periodoId)
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
        $data = $this->normalizarImportesOpcionales($this->resolverProveedorPorCuit($data, $tenantId));
        $this->assertReferencias($data, $empresaId, $tenantId);
        $this->assertNoDuplicado($data, $empresaId, $id);

        [$header, $lineas, $percepciones] = $this->preparar($data, $tenantId, $empresaId);
        $this->activarProveedorSiCorresponde($header, $empresaId);

        return $this->db->withTransaction(
            fn () => $this->compras->replace($id, $header, $lineas, $percepciones, $periodoId)
        );
    }

    public function delete(int $id, int $empresaId, int $periodoId, string $tenantId): void
    {
        $this->assertPeriodoEditable($empresaId, $periodoId, $tenantId);
        $this->compras->findById($id, $periodoId);

        $this->db->withTransaction(fn () => $this->compras->delete($id, $periodoId));
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
        $compra = $this->compras->findById($id, $periodoId);

        $destino = $this->assertPeriodoEditable($empresaId, $periodoDestinoId, $tenantId);
        $this->assertFechaEnPeriodo($compra['fecha'] ?? null, $destino);

        $this->db->withTransaction(
            fn () => $this->compras->moverAPeriodo($id, $periodoId, $periodoDestinoId)
        );

        return $this->compras->findById($id, $periodoDestinoId);
    }

    /**
     * Corre la calculadora y arma [cabecera con total, líneas (+ cf_computable), percepciones].
     *
     * @param  array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private function preparar(array $data, string $tenantId, int $empresaId): array
    {
        $cuentaDefault = !empty($data['proveedor_id'])
            ? $this->imputacion->resolverCuenta(
                $empresaId,
                (int) $data['proveedor_id'],
                isset($data['punto_venta']) ? (string) $data['punto_venta'] : null,
            )
            : null;
        $lineasInput = $this->normalizarDiscriminaciones($data['discriminaciones'] ?? [], $cuentaDefault);
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
            $ivaImporte = $calc['lineas'][$i]['iva_importe'];
            $lineas[] = [
                'neto_gravado'     => $calc['lineas'][$i]['neto_gravado'],
                'cuenta_id'        => $linea['cuenta_id'],
                'iva_alicuota'     => $linea['iva_alicuota'],
                'iva_importe'      => $ivaImporte,
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'],
                'iva_inc_importe'  => $calc['lineas'][$i]['iva_inc_importe'],
                // Crédito fiscal computable: informado, o el IVA completo por defecto.
                'cf_computable'    => $linea['cf_computable'] ?? $ivaImporte,
            ];
        }

        return [$header, $lineas, $percepciones];
    }

    /**
     * Resuelve cada percepción sufrida del comprobante: toma del tipo de retención la base
     * de cálculo y la alícuota por defecto, y delega en PercepcionCalculator base e importe
     * (que integran el total). Un importe/base/alícuota informados pisan los del tipo.
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
     * Valida y normaliza las líneas de discriminación. `$cuentaDefault` (resuelta por
     * `ImputacionContableRepository` a partir del proveedor+punto de venta) se usa solo cuando
     * la línea no trae `cuenta_id` propio — un override manual siempre gana.
     *
     * @param  mixed $discriminaciones
     * @return list<array<string, mixed>>
     */
    private function normalizarDiscriminaciones(mixed $discriminaciones, ?int $cuentaDefault): array
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

            $out[] = [
                'neto_gravado'     => $linea['neto_gravado'],
                'cuenta_id'        => $this->normalizarCuentaId($linea['cuenta_id'] ?? null) ?? $cuentaDefault,
                'iva_alicuota'     => $linea['iva_alicuota'],
                // Override opcional del importe de IVA (regla del asterisco): sólo si es numérico.
                'iva_importe'      => $this->esNumerico($linea['iva_importe'] ?? null) ? $linea['iva_importe'] : null,
                'iva_inc_alicuota' => $linea['iva_inc_alicuota'] ?? null,
                'cf_computable'    => $cf,
            ];
        }

        return $out;
    }

    /**
     * Valida y normaliza las percepciones sufridas del comprobante (a nivel cabecera). Cada
     * una requiere `tipo_retencion_id`; `alicuota`, `base`, `importe` y `provincia_id` son
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
     * Valida que las FKs de la compra existan y pertenezcan al ámbito: rubro del tenant,
     * proveedor de la empresa; el resto son catálogos globales. Devuelve 422 (no 500 por FK).
     *
     * @param array<string, mixed> $data
     */
    private function assertReferencias(array $data, int $empresaId, string $tenantId): void
    {
        $this->refs->validate([
            'tipo_comprobante_id'      => [
                'table' => 'tipos_comprobante', 'value' => $data['tipo_comprobante_id'] ?? null,
            ],
            'tipo_documento_id'        => ['table' => 'tipos_documento', 'value' => $data['tipo_documento_id'] ?? null],
            'condicion_iva_id'         => ['table' => 'condiciones_iva', 'value' => $data['condicion_iva_id'] ?? null],
            'provincia_id'             => ['table' => 'provincias', 'value' => $data['provincia_id'] ?? null],
            'tipo_operacion_compra_id' => [
                'table' => 'tipos_operacion_compra', 'value' => $data['tipo_operacion_compra_id'] ?? null,
            ],
            'tipo_moneda_id'           => ['table' => 'tipos_moneda', 'value' => $data['tipo_moneda_id'] ?? null],
            'rubro_id'                 => [
                'table' => 'rubros', 'value' => $data['rubro_id'] ?? null, 'scope' => ['tenant_id' => $tenantId],
            ],
            'proveedor_id'             => [
                'table' => 'iva_sujetos', 'value' => $data['proveedor_id'] ?? null,
                'scope' => ['tenant_id' => $tenantId],
            ],
            'cuenta_debe_id'           => [
                'table' => 'cuentas', 'value' => $data['cuenta_debe_id'] ?? null,
                'scope' => ['empresa_id' => $empresaId],
            ],
            'cuenta_haber_id'          => [
                'table' => 'cuentas', 'value' => $data['cuenta_haber_id'] ?? null,
                'scope' => ['empresa_id' => $empresaId],
            ],
        ]);
    }

    /**
     * Si no viene `proveedor_id` pero sí `cuit` (caso del importador, que hoy solo manda
     * `proveedor_nombre`/`cuit` en texto libre — nunca intenta matchear contra el padrón), busca
     * ese CUIT en `iva_sujetos` del tenant y completa `proveedor_id` si matchea. No falla si no
     * hay match: el comprobante sigue creándose como "sujeto ocasional", igual que hoy.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolverProveedorPorCuit(array $data, string $tenantId): array
    {
        if (!empty($data['proveedor_id']) || empty($data['cuit'])) {
            return $data;
        }

        $sujeto = $this->sujetos->findByCuit($tenantId, Cuit::normalizar((string) $data['cuit']));
        if ($sujeto !== null) {
            $data['proveedor_id'] = $sujeto['id'];
        }

        return $data;
    }

    /**
     * Importes NOT NULL con DEFAULT en la tabla: un null del payload significa "no informado"
     * (el modal manda null cuando el campo quedó vacío) — se normaliza a 0 para que el
     * INSERT/UPDATE no intente escribir NULL en una columna que no lo admite. `concepto`
     * (smallint NOT NULL) se descarta si viene null (toma su DEFAULT al crear).
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizarImportesOpcionales(array $data): array
    {
        foreach (['neto_no_grav', 'exento', 'imp_interno'] as $campo) {
            if (array_key_exists($campo, $data) && $data[$campo] === null) {
                $data[$campo] = '0';
            }
        }
        if (array_key_exists('concepto', $data) && $data['concepto'] === null) {
            unset($data['concepto']);
        }

        return $data;
    }

    /**
     * Cargar una compra con un proveedor del Padrón Único lo activa para esta empresa
     * (aparece en su listado "Proveedores") sin paso manual extra.
     *
     * @param array<string, mixed> $header
     */
    private function activarProveedorSiCorresponde(array $header, int $empresaId): void
    {
        if (!empty($header['proveedor_id'])) {
            $this->sujetoEmpresas->activar($empresaId, (int) $header['proveedor_id'], 'proveedor');
        }
    }

    /**
     * Evita cargar dos veces el mismo comprobante de compra en la empresa (mismo proveedor
     * por CUIT + tipo + letra + punto de venta + número). Se omite si falta punto de venta
     * o número (no hay clave para comparar).
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

        $dupId = $this->compras->findDuplicado(
            $empresaId,
            $data['cuit'] ?? null,
            isset($data['tipo_comprobante_id']) ? (int) $data['tipo_comprobante_id'] : null,
            $data['letra'] ?? null,
            $pv,
            $numero,
            $exceptId,
        );

        if ($dupId !== null) {
            throw new ConflictException(
                "Ya existe ese comprobante del proveedor (punto de venta {$pv}, número {$numero})."
            );
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
