<?php

namespace App\Modules\Iva\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Support\Calc\Decimal;
use App\Modules\Iva\Calc\LibroIvaCalculator;
use App\Modules\Iva\Calc\LibroIvaDetalleCalculator;
use App\Modules\Iva\Calc\DeclaracionIvaCalculator;
use App\Modules\Iva\Calc\IvaSimpleCalculator;
use App\Modules\Iva\Repositories\LibroIvaRepository;

/**
 * Totales del período (libro IVA), derivados on-the-fly: el repositorio agrega por
 * signo y la calculadora compone los totales firmados y el saldo de IVA. Valida
 * que la empresa pertenezca al tenant y el período a la empresa.
 */
class LibroIvaService
{
    public function __construct(
        private LibroIvaRepository $libro,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private LibroIvaCalculator $calculator,
        private LibroIvaDetalleCalculator $detalleCalculator,
        private DeclaracionIvaCalculator $declaracionCalculator,
        private IvaSimpleCalculator $ivaSimpleCalculator,
    ) {
    }

    /**
     * @return array{
     *   total_ventas: string, total_compras: string,
     *   iva_ventas: string, iva_compras: string, saldo_iva: string
     * }
     */
    public function totales(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        return $this->calculator->totalesPeriodo(
            $this->libro->ventasTotalPorSigno($periodoId),
            $this->libro->ventasIvaPorSigno($periodoId),
            $this->libro->comprasTotalPorSigno($periodoId),
            $this->libro->comprasIvaPorSigno($periodoId),
        );
    }

    /**
     * Libro IVA detallado: subtotales por (condición IVA, alícuota) de ventas y
     * compras, ponderados por signo. Base de las DDJJ.
     *
     * @return array{ventas: list<array<string, mixed>>, compras: list<array<string, mixed>>}
     */
    public function detalle(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        return [
            'ventas' => $this->detalleCalculator->agruparPorAlicuota(
                $this->libro->ventasDetallePorAlicuota($periodoId),
                ['neto_gravado', 'iva'],
            ),
            'compras' => $this->detalleCalculator->agruparPorAlicuota(
                $this->libro->comprasDetallePorAlicuota($periodoId),
                ['neto_gravado', 'iva', 'cf_computable'],
            ),
        ];
    }

    /**
     * Declaración jurada de IVA del período (formato F2002): débito fiscal (ventas),
     * crédito fiscal computable (compras), conceptos y saldo técnico.
     *
     * @return array<string, mixed>
     */
    public function declaracion(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        $ventasDetalle = $this->detalleCalculator->agruparPorAlicuota(
            $this->libro->ventasDetallePorAlicuota($periodoId),
            ['neto_gravado', 'iva'],
        );
        $comprasDetalle = $this->detalleCalculator->agruparPorAlicuota(
            $this->libro->comprasDetallePorAlicuota($periodoId),
            ['neto_gravado', 'iva', 'cf_computable'],
        );

        $consolidado = $this->declaracionCalculator->consolidar($ventasDetalle, $comprasDetalle);

        return [
            'debito_fiscal' => [
                'por_alicuota' => $ventasDetalle,
                'neto_gravado' => $consolidado['debito_neto'],
                'iva'          => $consolidado['debito_iva'],
            ],
            'credito_fiscal' => [
                'por_alicuota' => $comprasDetalle,
                'neto_gravado' => $consolidado['credito_neto'],
                'iva'          => $consolidado['credito_iva'],
                'computable'   => $consolidado['credito_computable'],
            ],
            'conceptos_ventas'  => $this->formatearConceptos($this->libro->ventasConceptos($periodoId)),
            'conceptos_compras' => $this->formatearConceptos($this->libro->comprasConceptos($periodoId)),
            'saldo' => [
                'debito_fiscal'      => $consolidado['debito_iva'],
                'credito_computable' => $consolidado['credito_computable'],
                'saldo_tecnico'      => $consolidado['saldo_tecnico'],
            ],
        ];
    }

    /**
     * DDJJ de IVA "IVA Simple" del período (F.2051 del Portal IVA, reemplaza al F2002):
     * encadena el débito/crédito computable del período con los arrastres (saldo técnico
     * anterior, saldo de libre disponibilidad anterior y retenciones/percepciones/pagos a
     * cuenta), que son insumos del estudio. Ver {@see IvaSimpleCalculator}.
     *
     * @return array<string, mixed>
     */
    public function ivaSimple(
        int $empresaId,
        int $periodoId,
        string $tenantId,
        string $saldoTecnicoAnterior = '0',
        string $saldoLibreDisponibilidadAnterior = '0',
        string $retencionesPercepcionesPagos = '0',
    ): array {
        $declaracion = $this->declaracion($empresaId, $periodoId, $tenantId);

        $f2051 = $this->ivaSimpleCalculator->calcular(
            $declaracion['saldo']['debito_fiscal'],
            $declaracion['saldo']['credito_computable'],
            $saldoTecnicoAnterior,
            $saldoLibreDisponibilidadAnterior,
            $retencionesPercepcionesPagos,
        );

        return [
            'debito_fiscal'  => $declaracion['debito_fiscal'],
            'credito_fiscal' => $declaracion['credito_fiscal'],
            'determinacion_impuesto' => $f2051['determinacion_impuesto'],
            'posicion_mensual'       => $f2051['posicion_mensual'],
        ];
    }

    /**
     * @param  array<string, string> $conceptos
     * @return array<string, string>
     */
    private function formatearConceptos(array $conceptos): array
    {
        return array_map(static fn (string $v) => Decimal::of($v)->value(2), $conceptos);
    }
}
