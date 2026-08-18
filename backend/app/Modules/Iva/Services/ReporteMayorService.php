<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Calc\MayorReporteCalculator;
use App\Modules\Iva\Repositories\ReporteMayorRepository;

/**
 * Reporte analítico del Mayor por rango de fechas (R2 del contador): toma los movimientos
 * por línea (neto imputado a su cuenta) de un rango, con filtros, y los agrupa en cascada
 * con subtotales. Valida la pertenencia empresa∈tenant.
 */
class ReporteMayorService
{
    /**
     * Tope de líneas traíbles a memoria en un solo reporte. Muy por encima de un mes real de
     * una empresa grande (ej. REYMUNDO FRIAS: ~7.000 comprobantes/mes) y muy por debajo de lo
     * que agota los 128M por defecto de PHP con el histórico completo de una empresa así.
     */
    private const MAX_MOVIMIENTOS = 20000;

    public function __construct(
        private ReporteMayorRepository $datos,
        private MayorReporteCalculator $calc,
        private EmpresaRepository $empresas,
    ) {
    }

    /**
     * @param  array<string, mixed> $query desde, hasta, cuenta_id, provincia_id, cuit, origen, agrupar
     * @return array<string, mixed> grupos (cascada), totales, filtros aplicados, agrupar (dimensiones)
     */
    public function reporte(int $empresaId, string $tenantId, array $query): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        $filtros = [
            'desde'        => $this->str($query['desde'] ?? null),
            'hasta'        => $this->str($query['hasta'] ?? null),
            'cuenta_id'    => $this->int($query['cuenta_id'] ?? null),
            'provincia_id' => $this->int($query['provincia_id'] ?? null),
            'cuit'         => $this->str($query['cuit'] ?? null),
            'origen'       => in_array($query['origen'] ?? null, ['venta', 'compra'], true)
                ? (string) $query['origen']
                : null,
        ];

        // Sin rango, el repo trae TODO el histórico de la empresa (cientos de miles de líneas
        // reales) en un fetchAll() sin límite — es lo que agotaba los 128M por defecto de PHP.
        if ($filtros['desde'] === null || $filtros['hasta'] === null) {
            throw new ValidationException([
                'desde' => ['Elegí un rango de fechas (desde y hasta) para generar el reporte.'],
            ]);
        }

        $cantidad = $this->datos->contarMovimientos($empresaId, $filtros);
        if ($cantidad > self::MAX_MOVIMIENTOS) {
            throw new ValidationException([
                'desde' => [
                    "Hay {$cantidad} movimientos en ese rango — acotá las fechas o agregá un"
                    . ' filtro de cuenta/proveedor/provincia para poder generar el reporte.',
                ],
            ]);
        }

        $agrupar = $this->agrupar($query['agrupar'] ?? null);

        $movimientos = $this->datos->movimientos($empresaId, $filtros);
        $resultado   = $this->calc->agrupar($movimientos, $agrupar);

        return [
            'grupos'  => $resultado['grupos'],
            'totales' => $resultado['totales'],
            'filtros' => $filtros,
            'agrupar' => $agrupar,
        ];
    }

    /**
     * Dimensiones de agrupación: coma-separadas (ej. "cuenta,proveedor"). Default cuenta→proveedor.
     *
     * @return list<string>
     */
    private function agrupar(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return ['cuenta', 'proveedor'];
        }

        $dims = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $dims === [] ? ['cuenta', 'proveedor'] : $dims;
    }

    private function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    private function int(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }
}
