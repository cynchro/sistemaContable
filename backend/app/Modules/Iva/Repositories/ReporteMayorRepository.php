<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Movimientos del Mayor por RANGO de fechas (no atados a un período), para los reportes
 * analíticos que pide el contador (R2, 07/07): DDJJ anual de convenio ("gastos computables
 * por provincia y categoría"), "principales proveedores del último año", etc.
 *
 * Devuelve un renglón por LÍNEA de discriminación (el neto imputado a su cuenta, con signo),
 * enriquecido con cuenta, sujeto (proveedor/cliente), CUIT, provincia y datos del comprobante.
 * La agregación en cascada + subtotales la hace {@see \App\Modules\Iva\Calc\MayorReporteCalculator}.
 *
 * Filtros (todos opcionales): desde/hasta (fecha), cuenta_id, provincia_id, cuit, origen.
 * La pertenencia empresa∈tenant la valida el Service.
 */
class ReporteMayorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param  array<string, mixed> $filtros claves: desde, hasta, cuenta_id, provincia_id, cuit, origen
     * @return list<array<string, mixed>>
     */
    public function movimientos(int $empresaId, array $filtros): array
    {
        [$condCompra, $paramsCompra] = $this->condiciones('c', $empresaId, $filtros);
        [$condVenta, $paramsVenta]   = $this->condiciones('v', $empresaId, $filtros);

        $incluirCompras = ($filtros['origen'] ?? null) !== 'venta';
        $incluirVentas  = ($filtros['origen'] ?? null) !== 'compra';

        $sqls   = [];
        $params = [];
        if ($incluirCompras) {
            $sqls[] = "SELECT 'compra' AS origen, c.fecha,
                              cd.neto_gravado * tc.signo AS neto,
                              cd.iva_importe  * tc.signo AS iva,
                              cd.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre,
                              c.proveedor_nombre AS sujeto, c.cuit,
                              c.provincia_id, pr.nombre AS provincia_nombre,
                              tc.codigo AS cbte_codigo, c.letra, c.punto_venta, c.numero
                         FROM compra_discriminaciones cd
                         JOIN compras c   ON cd.compra_id = c.id
                         JOIN periodos pe ON c.periodo_id = pe.id
                         JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                         LEFT JOIN cuentas    cu ON cd.cuenta_id   = cu.id
                         LEFT JOIN provincias pr ON c.provincia_id = pr.id
                        WHERE {$condCompra}";
            $params = [...$params, ...$paramsCompra];
        }
        if ($incluirVentas) {
            $sqls[] = "SELECT 'venta' AS origen, v.fecha,
                              vd.neto_gravado * tc.signo AS neto,
                              vd.iva_importe  * tc.signo AS iva,
                              vd.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre,
                              v.cliente_nombre AS sujeto, v.cuit,
                              v.provincia_id, pr.nombre AS provincia_nombre,
                              tc.codigo AS cbte_codigo, v.letra, v.punto_venta, v.numero
                         FROM venta_discriminaciones vd
                         JOIN ventas v    ON vd.venta_id = v.id
                         JOIN periodos pe ON v.periodo_id = pe.id
                         JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                         LEFT JOIN cuentas    cu ON vd.cuenta_id   = cu.id
                         LEFT JOIN provincias pr ON v.provincia_id = pr.id
                        WHERE {$condVenta}";
            $params = [...$params, ...$paramsVenta];
        }

        // Siempre queda al menos un lado (origen null ⇒ ambos; 'venta'/'compra' ⇒ uno).
        $stmt = $this->pdo->prepare(implode(' UNION ALL ', $sqls) . ' ORDER BY fecha, numero');
        $stmt->execute($params);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Igual que {@see self::movimientos()} pero solo cuenta filas, sin traerlas — usado como
     * guard rail antes de decidir si el rango pedido es traíble a memoria (ver
     * `ReporteMayorService::reporte`, evita repetir el crash de `fetchAll()` sin límite sobre
     * cientos de miles de líneas reales).
     *
     * @param  array<string, mixed> $filtros claves: desde, hasta, cuenta_id, provincia_id, cuit, origen
     */
    public function contarMovimientos(int $empresaId, array $filtros): int
    {
        [$condCompra, $paramsCompra] = $this->condiciones('c', $empresaId, $filtros);
        [$condVenta, $paramsVenta]   = $this->condiciones('v', $empresaId, $filtros);

        $incluirCompras = ($filtros['origen'] ?? null) !== 'venta';
        $incluirVentas  = ($filtros['origen'] ?? null) !== 'compra';

        $sqls   = [];
        $params = [];
        if ($incluirCompras) {
            $sqls[] = "SELECT 1 FROM compra_discriminaciones cd
                         JOIN compras c   ON cd.compra_id = c.id
                         JOIN periodos pe ON c.periodo_id = pe.id
                        WHERE {$condCompra}";
            $params = [...$params, ...$paramsCompra];
        }
        if ($incluirVentas) {
            $sqls[] = "SELECT 1 FROM venta_discriminaciones vd
                         JOIN ventas v    ON vd.venta_id = v.id
                         JOIN periodos pe ON v.periodo_id = pe.id
                        WHERE {$condVenta}";
            $params = [...$params, ...$paramsVenta];
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM (' . implode(' UNION ALL ', $sqls) . ') AS sub'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Arma el WHERE (empresa + filtros) para un lado. `$alias` = 'c' (compras) | 'v' (ventas);
     * las columnas de fecha/provincia/cuit viven en la cabecera, cuenta_id en la discriminación.
     *
     * @param  array<string, mixed> $filtros claves: desde, hasta, cuenta_id, provincia_id, cuit, origen
     * @return array{0: string, 1: list<mixed>}
     */
    private function condiciones(string $alias, int $empresaId, array $filtros): array
    {
        $disAlias = $alias === 'c' ? 'cd' : 'vd';
        $cond     = ['pe.empresa_id = ?'];
        $params   = [$empresaId];

        if (($filtros['desde'] ?? null) !== null) {
            $cond[]   = "{$alias}.fecha >= ?";
            $params[] = $filtros['desde'];
        }
        if (($filtros['hasta'] ?? null) !== null) {
            $cond[]   = "{$alias}.fecha <= ?";
            $params[] = $filtros['hasta'];
        }
        if (($filtros['cuenta_id'] ?? null) !== null) {
            $cond[]   = "{$disAlias}.cuenta_id = ?";
            $params[] = $filtros['cuenta_id'];
        }
        if (($filtros['provincia_id'] ?? null) !== null) {
            $cond[]   = "{$alias}.provincia_id = ?";
            $params[] = $filtros['provincia_id'];
        }
        if (($filtros['cuit'] ?? null) !== null && $filtros['cuit'] !== '') {
            $cond[]   = "{$alias}.cuit = ?";
            $params[] = $filtros['cuit'];
        }

        return [implode(' AND ', $cond), $params];
    }
}
