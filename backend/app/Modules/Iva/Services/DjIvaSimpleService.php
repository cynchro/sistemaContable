<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Support\Calc\Decimal;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Export\DjIvaSimpleWriter;
use App\Modules\Iva\Repositories\DjIvaSimpleRepository;
use App\Modules\Iva\Repositories\EmpresaActividadRepository;

/**
 * Exporta los 4 archivos de "Apertura de otros conceptos" de la DJ IVA Simple
 * (Portal IVA). Distribuye la operatoria del período **por actividad** (la actividad
 * de cada venta se resuelve por override del comprobante o por el mapa de puntos de
 * venta; ver DjIvaSimpleRepository) y delega el formato CSV en {@see DjIvaSimpleWriter}.
 *
 * Análisis y estrategias: docs/ingenieria-inversa/dj-iva-simple-actividad.md (v2).
 */
class DjIvaSimpleService
{
    /** @var array<string, string> slug => nombre del archivo */
    private const ARCHIVOS = [
        'debito-fiscal'        => 'DJ_IVA_SIMPLE_DEBITO_FISCAL',
        'restitucion-debito'   => 'DJ_IVA_SIMPLE_RESTITUCION_DEBITO',
        'credito-fiscal'       => 'DJ_IVA_SIMPLE_CREDITO_FISCAL',
        'restitucion-credito'  => 'DJ_IVA_SIMPLE_RESTITUCION_CREDITO',
    ];

    public function __construct(
        private DjIvaSimpleRepository $datos,
        private DjIvaSimpleWriter $writer,
        private EmpresaActividadRepository $actividades,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
    ) {
    }

    /**
     * Genera el archivo pedido (por slug). Devuelve nombre y contenido CSV.
     *
     * @return array{nombre: string, contenido: string}
     */
    public function exportar(int $empresaId, int $periodoId, string $tenantId, string $slug): array
    {
        if (!isset(self::ARCHIVOS[$slug])) {
            throw new ValidationException(['archivo' => ["Archivo de DJ IVA Simple desconocido: '{$slug}'."]]);
        }

        $empresa = $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        $contenido = match ($slug) {
            'debito-fiscal'       => $this->debito($empresaId, $periodoId, $empresa, 1),
            'restitucion-debito'  => $this->restitucionDebito($empresaId, $periodoId, $empresa),
            'credito-fiscal'      => $this->writer->creditoFiscal($this->datos->comprasGravado($periodoId, 1)),
            'restitucion-credito' => $this->writer->restitucionCredito($this->datos->comprasGravado($periodoId, -1)),
        };

        return ['nombre' => self::ARCHIVOS[$slug] . '.csv', 'contenido' => $contenido];
    }

    /**
     * Archivo de Débito Fiscal: agrupa por actividad, separando ventas comunes (tipo op 1),
     * bienes de uso (tipo op 2) y exento/no gravado (tipo op 3).
     *
     * @param array<string, mixed> $empresa
     */
    private function debito(int $empresaId, int $periodoId, array $empresa, int $signo): string
    {
        $coeficientes = $this->actividades->coeficientes($empresaId);
        if ($coeficientes !== []) {
            return $this->debitoPorCoeficientes($periodoId, $signo, $coeficientes);
        }

        $default   = $this->actividadDefault($empresaId, $empresa);
        $gravado   = $this->datos->ventasGravado($empresaId, $periodoId, $signo);
        $noGravado = $this->datos->ventasNoGravado($empresaId, $periodoId, $signo);

        /** @var array<string, array{normal: list<array<string,mixed>>, bien_uso: list<array<string,mixed>>, no_grav: string}> $porAct */
        $porAct = [];
        $ref = function (string $act) use (&$porAct): void {
            if (!isset($porAct[$act])) {
                $porAct[$act] = ['normal' => [], 'bien_uso' => [], 'no_grav' => '0'];
            }
        };

        foreach ($gravado as $row) {
            $act = $row['actividad_codigo'] ?? $default;
            $ref($act);
            $porAct[$act][$row['es_bien_uso'] === 'S' ? 'bien_uso' : 'normal'][] = $row;
        }
        foreach ($noGravado as $row) {
            $act = $row['actividad_codigo'] ?? $default;
            $ref($act);
            $porAct[$act]['no_grav'] = $row['monto'];
        }

        $out = '';
        foreach ($porAct as $act => $g) {
            $out .= $this->writer->debitoFiscal($act, $g['normal'], $g['bien_uso'], $g['no_grav']);
        }

        return $out;
    }

    /**
     * Archivo de Restitución de Débito (notas de crédito de ventas). Por actividad;
     * los bienes de uso van integrados al tipo op 1 (la spec no los separa en restitución).
     *
     * @param array<string, mixed> $empresa
     */
    private function restitucionDebito(int $empresaId, int $periodoId, array $empresa): string
    {
        $coeficientes = $this->actividades->coeficientes($empresaId);
        if ($coeficientes !== []) {
            return $this->restitucionPorCoeficientes($periodoId, $coeficientes);
        }

        $default   = $this->actividadDefault($empresaId, $empresa);
        $gravado   = $this->datos->ventasGravado($empresaId, $periodoId, -1);
        $noGravado = $this->datos->ventasNoGravado($empresaId, $periodoId, -1);

        /** @var array<string, array{gravado: list<array<string,mixed>>, no_grav: string}> $porAct */
        $porAct = [];
        foreach ($gravado as $row) {
            $act = $row['actividad_codigo'] ?? $default;
            $porAct[$act] ??= ['gravado' => [], 'no_grav' => '0'];
            $porAct[$act]['gravado'][] = $row;
        }
        foreach ($noGravado as $row) {
            $act = $row['actividad_codigo'] ?? $default;
            $porAct[$act] ??= ['gravado' => [], 'no_grav' => '0'];
            $porAct[$act]['no_grav'] = $row['monto'];
        }

        $out = '';
        foreach ($porAct as $act => $g) {
            $out .= $this->writer->restitucionDebito($act, $g['gravado'], $g['no_grav']);
        }

        return $out;
    }

    /**
     * Modo porcentajes fijos (Fase 3): reparte el débito del período entre las actividades
     * según su coeficiente (neto×coef, IVA×coef). Bienes de uso → tipo op 2.
     *
     * @param list<array<string, mixed>> $coeficientes
     */
    private function debitoPorCoeficientes(int $periodoId, int $signo, array $coeficientes): string
    {
        $totales = $this->datos->ventasGravadoTotal($periodoId, $signo);
        $noGrav  = $this->datos->ventasNoGravadoTotal($periodoId, $signo);

        $out = '';
        foreach ($coeficientes as $coef) {
            $c = (string) $coef['coeficiente'];
            $normal  = [];
            $bienUso = [];
            foreach ($totales as $row) {
                $linea = [
                    'condicion_iva_id' => $row['condicion_iva_id'],
                    'alicuota'         => $row['alicuota'],
                    'neto'             => $this->aplicar($row['neto'], $c),
                    'iva'              => $this->aplicar($row['iva'], $c),
                ];
                if ($row['es_bien_uso'] === 'S') {
                    $bienUso[] = $linea;
                } else {
                    $normal[] = $linea;
                }
            }
            $out .= $this->writer->debitoFiscal(
                (string) $coef['actividad_codigo'],
                $normal,
                $bienUso,
                $this->aplicar($noGrav, $c),
            );
        }

        return $out;
    }

    /**
     * Modo porcentajes fijos para la restitución (NC de ventas). Sin separar bienes de uso.
     *
     * @param list<array<string, mixed>> $coeficientes
     */
    private function restitucionPorCoeficientes(int $periodoId, array $coeficientes): string
    {
        $totales = $this->datos->ventasGravadoTotal($periodoId, -1);
        $noGrav  = $this->datos->ventasNoGravadoTotal($periodoId, -1);

        $out = '';
        foreach ($coeficientes as $coef) {
            $c = (string) $coef['coeficiente'];
            $gravado = [];
            foreach ($totales as $row) {
                $gravado[] = [
                    'condicion_iva_id' => $row['condicion_iva_id'],
                    'alicuota'         => $row['alicuota'],
                    'neto'             => $this->aplicar($row['neto'], $c),
                    'iva'              => $this->aplicar($row['iva'], $c),
                ];
            }
            $out .= $this->writer->restitucionDebito(
                (string) $coef['actividad_codigo'],
                $gravado,
                $this->aplicar($noGrav, $c),
            );
        }

        return $out;
    }

    /** monto × coeficiente, redondeado a 2 decimales. */
    private function aplicar(string $monto, string $coeficiente): string
    {
        return Decimal::of($monto)->mul(Decimal::of($coeficiente))->value(2);
    }

    /**
     * Actividad por defecto para comprobantes sin actividad resuelta: la primera
     * actividad cargada de la empresa, o la legacy `actividad1_id`. Si no hay ninguna,
     * 422 pidiendo configurar las actividades.
     *
     * @param array<string, mixed> $empresa
     */
    private function actividadDefault(int $empresaId, array $empresa): string
    {
        $codigo = $this->actividades->codigoDefault($empresaId)
            ?? ($empresa['actividad1_id'] !== null ? (string) $empresa['actividad1_id'] : null);

        if ($codigo === null || $codigo === '') {
            throw new ValidationException([
                'actividades' => ['La empresa no tiene actividades cargadas (requerido por la DJ por actividad).'],
            ]);
        }

        return $codigo;
    }
}
