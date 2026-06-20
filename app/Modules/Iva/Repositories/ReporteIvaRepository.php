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
}
