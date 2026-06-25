<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Datos de los reportes "Subdiario / Libro IVA" de ventas y compras: un renglón por
 * comprobante, enriquecido con los importes calculados (neto gravado, IVA, IVA inc.,
 * percepción —y crédito computable en compras—) y los nombres de los catálogos.
 *
 * Replica las vistas VIVENTAS / VICOMPRAS del legacy. Acotado a `periodo_id`; la
 * pertenencia del período la valida el Service.
 */
class ReporteIvaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function ventas(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.*,
                (SELECT COALESCE(SUM(vd.neto_gravado), 0) FROM venta_discriminaciones vd
                  WHERE vd.venta_id = v.id) AS neto_gravado,
                (SELECT COALESCE(SUM(vd.iva_importe), 0) - COALESCE(SUM(vd.reintegro_t), 0)
                   FROM venta_discriminaciones vd WHERE vd.venta_id = v.id) AS iva,
                (SELECT COALESCE(SUM(vd.iva_inc_importe), 0) FROM venta_discriminaciones vd
                  WHERE vd.venta_id = v.id) AS iva_inc,
                (SELECT COALESCE(SUM(vp.importe), 0) FROM venta_percepciones vp
                  WHERE vp.venta_id = v.id) AS percepcion,
                tc.codigo AS tipo_comprobante_codigo, tc.nombre AS tipo_comprobante_nombre, tc.cod_citi,
                td.nombre AS tipo_documento_nombre, td.cod_afip AS tipo_documento_cod_afip,
                pr.nombre AS provincia_nombre,
                ci.codigo AS condicion_codigo, ci.nombre AS condicion_nombre
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
               LEFT JOIN tipos_documento  td ON v.tipo_documento_id  = td.id
               LEFT JOIN provincias       pr ON v.provincia_id       = pr.id
               LEFT JOIN condiciones_iva  ci ON v.condicion_iva_id   = ci.id
              WHERE v.periodo_id = ?
              ORDER BY v.fecha, v.id"
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function compras(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                (SELECT COALESCE(SUM(cd.neto_gravado), 0) FROM compra_discriminaciones cd
                  WHERE cd.compra_id = c.id) AS neto_gravado,
                (SELECT COALESCE(SUM(cd.iva_importe), 0) FROM compra_discriminaciones cd
                  WHERE cd.compra_id = c.id) AS iva,
                (SELECT COALESCE(SUM(cd.iva_inc_importe), 0) FROM compra_discriminaciones cd
                  WHERE cd.compra_id = c.id) AS iva_inc,
                (SELECT COALESCE(SUM(cd.cf_computable), 0) FROM compra_discriminaciones cd
                  WHERE cd.compra_id = c.id) AS cf_computable,
                (SELECT COALESCE(SUM(cp.importe), 0) FROM compra_percepciones cp
                  WHERE cp.compra_id = c.id) AS percepcion,
                tc.codigo AS tipo_comprobante_codigo, tc.nombre AS tipo_comprobante_nombre, tc.cod_citi,
                pr.nombre AS provincia_nombre,
                ci.codigo AS condicion_codigo, ci.nombre AS condicion_nombre,
                op.nombre AS tipo_operacion_nombre
               FROM compras c
               LEFT JOIN tipos_comprobante      tc ON c.tipo_comprobante_id      = tc.id
               LEFT JOIN provincias             pr ON c.provincia_id             = pr.id
               LEFT JOIN condiciones_iva        ci ON c.condicion_iva_id         = ci.id
               LEFT JOIN tipos_operacion_compra op ON c.tipo_operacion_compra_id = op.id
              WHERE c.periodo_id = ?
              ORDER BY c.fecha, c.id"
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Percepciones de ventas agrupadas por tipo (y provincia), con base e importe.
     * Reporte secundario de retenciones/percepciones del período.
     *
     * @return list<array<string, mixed>>
     */
    public function percepcionesVentas(int $periodoId): array
    {
        return $this->percepciones($periodoId, 'venta_percepciones', 'venta_id', 'ventas');
    }

    /** @return list<array<string, mixed>> */
    public function percepcionesCompras(int $periodoId): array
    {
        return $this->percepciones($periodoId, 'compra_percepciones', 'compra_id', 'compras');
    }

    /**
     * @param  'venta_percepciones'|'compra_percepciones' $tabla
     * @param  'venta_id'|'compra_id'                      $fk
     * @param  'ventas'|'compras'                          $comprobantes
     * @return list<array<string, mixed>>
     */
    private function percepciones(int $periodoId, string $tabla, string $fk, string $comprobantes): array
    {
        // $tabla/$fk/$comprobantes son literales fijos (no input de usuario): sin riesgo de inyección.
        $stmt = $this->pdo->prepare(
            "SELECT p.tipo_retencion_id,
                    tr.nombre AS tipo_nombre, tr.cod_afip AS tipo_cod_afip, tr.tipo_rg3685,
                    pr.nombre AS provincia_nombre,
                    COUNT(*) AS cantidad, SUM(p.base) AS base, SUM(p.importe) AS importe
               FROM {$tabla} p
               JOIN {$comprobantes} cmp ON p.{$fk} = cmp.id
               LEFT JOIN tipos_retencion tr ON p.tipo_retencion_id = tr.id
               LEFT JOIN provincias      pr ON p.provincia_id      = pr.id
              WHERE cmp.periodo_id = ?
              GROUP BY p.tipo_retencion_id, tr.nombre, tr.cod_afip, tr.tipo_rg3685, pr.nombre
              ORDER BY tr.nombre, pr.nombre"
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
