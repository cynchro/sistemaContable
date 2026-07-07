<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Mayor de cuentas (mayorización interna): a partir de la imputación contable de cada
 * comprobante (cuenta_debe_id / cuenta_haber_id), arma el "Resumen de Movimientos"
 * (total por cuenta) y el "Detalle de Movimientos" (comprobantes de una cuenta) del
 * Visual IVA. El importe es el total del comprobante con su signo (las NC restan).
 */
class MayorRepository
{
    /**
     * Resumen por cuenta del período: debe, haber, saldo (debe − haber) y cantidad de
     * movimientos. Une los 4 lados (ventas/compras × debe/haber).
     *
     * @return list<array<string, mixed>>
     */
    public function resumen(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cu.id, cu.codigo, cu.nombre,
                    COALESCE(SUM(m.debe), 0)  AS debe,
                    COALESCE(SUM(m.haber), 0) AS haber,
                    COALESCE(SUM(m.debe - m.haber), 0) AS saldo,
                    COUNT(*) AS movimientos
               FROM (' . $this->movimientos() . ') m
               JOIN cuentas cu ON cu.id = m.cuenta_id
              GROUP BY cu.id, cu.codigo, cu.nombre
              ORDER BY cu.codigo, cu.nombre'
        );
        $stmt->execute([$periodoId, $periodoId, $periodoId, $periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de los comprobantes imputados a una cuenta (como debe o haber) en el período.
     *
     * @return list<array<string, mixed>>
     */
    public function detalle(int $periodoId, int $cuentaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT x.origen, x.lado, x.fecha, x.cbte_codigo, x.letra, x.punto_venta, x.numero,
                    x.nombre, x.importe
               FROM (
                    SELECT 'venta' AS origen, 'debe' AS lado, v.fecha, tc.codigo AS cbte_codigo,
                           v.letra, v.punto_venta, v.numero, v.cliente_nombre AS nombre,
                           v.total * tc.signo AS importe, v.cuenta_debe_id AS cuenta_id
                      FROM ventas v JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                     WHERE v.periodo_id = ?
                    UNION ALL
                    SELECT 'venta', 'haber', v.fecha, tc.codigo, v.letra, v.punto_venta, v.numero,
                           v.cliente_nombre, v.total * tc.signo, v.cuenta_haber_id
                      FROM ventas v JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                     WHERE v.periodo_id = ?
                    UNION ALL
                    SELECT 'compra', 'debe', c.fecha, tc.codigo, c.letra, c.punto_venta, c.numero,
                           c.proveedor_nombre, c.total * tc.signo, c.cuenta_debe_id
                      FROM compras c JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                     WHERE c.periodo_id = ?
                    UNION ALL
                    SELECT 'compra', 'haber', c.fecha, tc.codigo, c.letra, c.punto_venta, c.numero,
                           c.proveedor_nombre, c.total * tc.signo, c.cuenta_haber_id
                      FROM compras c JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                     WHERE c.periodo_id = ?
               ) x
              WHERE x.cuenta_id = ?
              ORDER BY x.fecha, x.numero"
        );
        $stmt->execute([$periodoId, $periodoId, $periodoId, $periodoId, $cuentaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function __construct(private PDO $pdo)
    {
    }

    /** Subconsulta con los 4 lados de movimiento (cuenta_id, debe, haber). 4 placeholders de período. */
    private function movimientos(): string
    {
        return "SELECT v.cuenta_debe_id AS cuenta_id, v.total * tc.signo AS debe, 0 AS haber
                  FROM ventas v JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                 WHERE v.periodo_id = ? AND v.cuenta_debe_id IS NOT NULL
                UNION ALL
                SELECT v.cuenta_haber_id, 0, v.total * tc.signo
                  FROM ventas v JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                 WHERE v.periodo_id = ? AND v.cuenta_haber_id IS NOT NULL
                UNION ALL
                SELECT c.cuenta_debe_id, c.total * tc.signo, 0
                  FROM compras c JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                 WHERE c.periodo_id = ? AND c.cuenta_debe_id IS NOT NULL
                UNION ALL
                SELECT c.cuenta_haber_id, 0, c.total * tc.signo
                  FROM compras c JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                 WHERE c.periodo_id = ? AND c.cuenta_haber_id IS NOT NULL";
    }
}
