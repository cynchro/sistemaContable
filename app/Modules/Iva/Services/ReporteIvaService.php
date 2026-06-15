<?php

namespace App\Modules\Iva\Services;

use App\Support\Calc\Decimal;
use App\Support\Csv\CsvWriter;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Repositories\ReporteIvaRepository;

/**
 * Reportes "Subdiario / Libro IVA" de ventas y compras: el listado de comprobantes
 * del período (enriquecido por ReporteIvaRepository) con los totales al pie.
 *
 * Los totales al pie son la suma simple de las columnas listadas (lo que muestra el
 * subdiario). Las cifras netas/firmadas para liquidación viven en /totales y /ddjj.
 */
class ReporteIvaService
{
    /** @var list<string> Columnas de importe a totalizar en ventas. */
    private const COLS_VENTAS = [
        'neto_gravado', 'iva', 'iva_inc', 'retencion', 'neto_no_grav', 'exento', 'imp_interno', 'total',
    ];

    /** @var list<string> Columnas de importe a totalizar en compras. */
    private const COLS_COMPRAS = [
        'neto_gravado', 'iva', 'iva_inc', 'cf_computable', 'retencion',
        'neto_no_grav', 'exento', 'imp_interno', 'total',
    ];

    /** @var array<string, string> Columnas (clave => encabezado) del export de ventas. */
    private const EXPORT_VENTAS = [
        'fecha'                  => 'Fecha',
        'comprobante'            => 'Comprobante',
        'tipo_comprobante_nombre' => 'Tipo',
        'cliente_nombre'         => 'Cliente',
        'cuit'                   => 'CUIT',
        'condicion_nombre'       => 'Condicion IVA',
        'neto_gravado'           => 'Neto Gravado',
        'iva'                    => 'IVA',
        'neto_no_grav'           => 'No Gravado',
        'exento'                 => 'Exento',
        'imp_interno'            => 'Imp. Interno',
        'retencion'              => 'Retenciones',
        'total'                  => 'Total',
    ];

    /** @var array<string, string> Columnas (clave => encabezado) del export de compras. */
    private const EXPORT_COMPRAS = [
        'fecha'                  => 'Fecha',
        'comprobante'            => 'Comprobante',
        'tipo_comprobante_nombre' => 'Tipo',
        'proveedor_nombre'       => 'Proveedor',
        'cuit'                   => 'CUIT',
        'condicion_nombre'       => 'Condicion IVA',
        'neto_gravado'           => 'Neto Gravado',
        'iva'                    => 'IVA',
        'cf_computable'          => 'Cred. Fiscal Comp.',
        'neto_no_grav'           => 'No Gravado',
        'exento'                 => 'Exento',
        'imp_interno'            => 'Imp. Interno',
        'retencion'              => 'Retenciones',
        'total'                  => 'Total',
    ];

    public function __construct(
        private ReporteIvaRepository $reportes,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
    ) {
    }

    /** @return array{comprobantes: list<array<string, mixed>>, totales: array<string, string>} */
    public function libroVentas(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);
        $rows = $this->reportes->ventas($periodoId);

        return ['comprobantes' => $rows, 'totales' => $this->totalizar($rows, self::COLS_VENTAS)];
    }

    /** @return array{comprobantes: list<array<string, mixed>>, totales: array<string, string>} */
    public function libroCompras(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);
        $rows = $this->reportes->compras($periodoId);

        return ['comprobantes' => $rows, 'totales' => $this->totalizar($rows, self::COLS_COMPRAS)];
    }

    /** Exporta el subdiario de ventas a CSV/TXT delimitado. */
    public function exportarVentas(int $empresaId, int $periodoId, string $tenantId, string $delimitador): string
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return CsvWriter::generar(self::EXPORT_VENTAS, $this->reportes->ventas($periodoId), $delimitador);
    }

    /** Exporta el subdiario de compras a CSV/TXT delimitado. */
    public function exportarCompras(int $empresaId, int $periodoId, string $tenantId, string $delimitador): string
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return CsvWriter::generar(self::EXPORT_COMPRAS, $this->reportes->compras($periodoId), $delimitador);
    }

    private function assertPeriodo(int $empresaId, int $periodoId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);
    }

    /**
     * Suma cada columna a lo largo de las filas (importes exactos con el motor).
     *
     * @param  list<array<string, mixed>> $rows
     * @param  list<string>               $cols
     * @return array<string, string>
     */
    private function totalizar(array $rows, array $cols): array
    {
        $totales = array_fill_keys($cols, Decimal::zero());

        foreach ($rows as $row) {
            foreach ($cols as $col) {
                $totales[$col] = $totales[$col]->add(Decimal::of($row[$col] ?? 0));
            }
        }

        return array_map(static fn (Decimal $d) => $d->value(2), $totales);
    }
}
