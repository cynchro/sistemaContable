<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Mayor de cuentas (mayorización interna). Arma el "Resumen de Movimientos" (total por
 * cuenta) y el "Detalle de Movimientos" (comprobantes de una cuenta) del Visual IVA.
 *
 * Dos niveles de imputación (respuesta R1 del contador, 07/07):
 *  - Por LÍNEA de discriminación (`*_discriminaciones.cuenta_id`): el NETO de la línea se
 *    imputa a su cuenta (el gasto en compras / el ingreso en ventas). Un mismo comprobante
 *    puede repartirse en varias cuentas (ej. resumen bancario: 21% a "Gastos y comisiones",
 *    10,5% a "Intereses por giro en descubierto").
 *  - Por COMPROBANTE (`ventas/compras.cuenta_debe_id|cuenta_haber_id`, migración 0042): el
 *    TOTAL como contrapartida (proveedor/cliente/banco).
 *
 * El importe lleva el signo del tipo de comprobante (las NC restan).
 */
class MayorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Resumen por cuenta del período: debe, haber, saldo (debe − haber) y cantidad de
     * movimientos. Une los movimientos por línea (neto) y por comprobante (total).
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
        $stmt->execute(array_fill(0, 6, $periodoId));

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de los movimientos imputados a una cuenta en el período: renglón por línea
     * (neto) y por comprobante (total), con su origen y lado.
     *
     * @return list<array<string, mixed>>
     */
    public function detalle(int $periodoId, int $cuentaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT x.origen, x.nivel, x.lado, x.fecha, x.cbte_codigo, x.letra, x.punto_venta,
                    x.numero, x.nombre, x.importe
               FROM (' . $this->movimientosDetalle() . ') x
              WHERE x.cuenta_id = ?
              ORDER BY x.fecha, x.numero'
        );
        $stmt->execute([...array_fill(0, 6, $periodoId), $cuentaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Movimientos (cuenta_id, debe, haber) para el resumen. 6 placeholders de período:
     * 2 por línea (compra/venta) + 4 por comprobante (ventas/compras × debe/haber).
     */
    private function movimientos(): string
    {
        return "SELECT cd.cuenta_id, cd.neto_gravado * tc.signo AS debe, 0 AS haber
                  FROM compra_discriminaciones cd
                  JOIN compras c ON cd.compra_id = c.id
                  JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                 WHERE c.periodo_id = ? AND cd.cuenta_id IS NOT NULL
                UNION ALL
                SELECT vd.cuenta_id, 0, vd.neto_gravado * tc.signo
                  FROM venta_discriminaciones vd
                  JOIN ventas v ON vd.venta_id = v.id
                  JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                 WHERE v.periodo_id = ? AND vd.cuenta_id IS NOT NULL
                UNION ALL
                SELECT v.cuenta_debe_id, v.total * tc.signo, 0
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

    /**
     * Movimientos enriquecidos para el detalle (mismos 6 lados que {@see movimientos()},
     * con datos del comprobante). `nivel` = 'linea' (neto) | 'comprobante' (total).
     */
    private function movimientosDetalle(): string
    {
        return "SELECT 'compra' AS origen, 'linea' AS nivel, 'debe' AS lado, c.fecha,
                       tc.codigo AS cbte_codigo, c.letra, c.punto_venta, c.numero,
                       c.proveedor_nombre AS nombre, cd.neto_gravado * tc.signo AS importe,
                       cd.cuenta_id
                  FROM compra_discriminaciones cd
                  JOIN compras c ON cd.compra_id = c.id
                  JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                 WHERE c.periodo_id = ? AND cd.cuenta_id IS NOT NULL
                UNION ALL
                SELECT 'venta', 'linea', 'haber', v.fecha, tc.codigo, v.letra, v.punto_venta,
                       v.numero, v.cliente_nombre, vd.neto_gravado * tc.signo, vd.cuenta_id
                  FROM venta_discriminaciones vd
                  JOIN ventas v ON vd.venta_id = v.id
                  JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                 WHERE v.periodo_id = ? AND vd.cuenta_id IS NOT NULL
                UNION ALL
                SELECT 'venta', 'comprobante', 'debe', v.fecha, tc.codigo, v.letra, v.punto_venta,
                       v.numero, v.cliente_nombre, v.total * tc.signo, v.cuenta_debe_id
                  FROM ventas v JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                 WHERE v.periodo_id = ? AND v.cuenta_debe_id IS NOT NULL
                UNION ALL
                SELECT 'venta', 'comprobante', 'haber', v.fecha, tc.codigo, v.letra, v.punto_venta,
                       v.numero, v.cliente_nombre, v.total * tc.signo, v.cuenta_haber_id
                  FROM ventas v JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
                 WHERE v.periodo_id = ? AND v.cuenta_haber_id IS NOT NULL
                UNION ALL
                SELECT 'compra', 'comprobante', 'debe', c.fecha, tc.codigo, c.letra, c.punto_venta,
                       c.numero, c.proveedor_nombre, c.total * tc.signo, c.cuenta_debe_id
                  FROM compras c JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                 WHERE c.periodo_id = ? AND c.cuenta_debe_id IS NOT NULL
                UNION ALL
                SELECT 'compra', 'comprobante', 'haber', c.fecha, tc.codigo, c.letra, c.punto_venta,
                       c.numero, c.proveedor_nombre, c.total * tc.signo, c.cuenta_haber_id
                  FROM compras c JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
                 WHERE c.periodo_id = ? AND c.cuenta_haber_id IS NOT NULL";
    }
}
