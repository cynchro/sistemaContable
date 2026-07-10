<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Datos para el Libro IVA Digital (Portal IVA): una consulta por archivo
 * (cabeceras y alícuotas de ventas y compras). Las percepciones se agrupan por su
 * clasificación (`tipos_retencion.tipo_rg3685`) en los campos del diseño de ARCA.
 * Acotado a `periodo_id`; la pertenencia del período la valida el Service.
 */
class LibroIvaDigitalRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Suma de percepciones de una venta filtradas por clasificación RG. */
    private function percVenta(string $cond): string
    {
        return "(SELECT COALESCE(SUM(p.importe), 0) FROM venta_percepciones p
                  JOIN tipos_retencion tr ON tr.id = p.tipo_retencion_id
                 WHERE p.venta_id = v.id AND {$cond})";
    }

    /** Suma de percepciones de una compra filtradas por clasificación RG. */
    private function percCompra(string $cond): string
    {
        return "(SELECT COALESCE(SUM(p.importe), 0) FROM compra_percepciones p
                  JOIN tipos_retencion tr ON tr.id = p.tipo_retencion_id
                 WHERE p.compra_id = c.id AND {$cond})";
    }

    /** @return list<array<string, mixed>> Cabeceras de ventas del período. */
    public function ventasCbte(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.fecha, tc.codigo AS cbte_codigo, v.letra, v.punto_venta, v.numero, v.numero_fin,
                    td.cod_afip AS doc_cod_afip, v.cuit, v.cliente_nombre,
                    COALESCE(v.total_informado, v.total) AS total, v.neto_no_grav,
                    v.exento, v.imp_interno, v.tipo_cambio, mo.codigo_afip AS moneda_codigo,
                    (SELECT COUNT(*) FROM venta_discriminaciones vd WHERE vd.venta_id = v.id) AS cant_alic,
                    ' . $this->percVenta('tr.tipo_rg3685 = 5') . ' AS perc_no_cat,
                    ' . $this->percVenta('tr.tipo_rg3685 IN (1, 2)') . ' AS perc_nac,
                    ' . $this->percVenta('tr.tipo_rg3685 = 3') . ' AS perc_iibb,
                    ' . $this->percVenta('tr.tipo_rg3685 = 4') . ' AS perc_muni
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
               LEFT JOIN tipos_documento   td ON v.tipo_documento_id   = td.id
               LEFT JOIN tipos_moneda      mo ON v.tipo_moneda_id       = mo.id
              WHERE v.periodo_id = ?
              ORDER BY v.fecha, v.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> Renglón por alícuota de cada venta del período. */
    public function ventasAlicuotas(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tc.codigo AS cbte_codigo, v.letra, v.punto_venta, v.numero,
                    vd.neto_gravado, vd.iva_alicuota, vd.iva_importe
               FROM venta_discriminaciones vd
               JOIN ventas v ON v.id = vd.venta_id
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ?
              ORDER BY v.fecha, v.id, vd.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> Comprobantes de venta ANULADOS del período. */
    public function ventasAnulados(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.fecha, tc.codigo AS cbte_codigo, v.letra, v.punto_venta, v.numero, v.fecha_anulacion
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ? AND v.anulado = \'S\'
              ORDER BY v.fecha, v.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> Cabeceras de compras del período. */
    public function comprasCbte(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.fecha, tc.codigo AS cbte_codigo, c.letra, c.punto_venta, c.numero,
                    c.cuit, c.proveedor_nombre, COALESCE(c.total_informado, c.total) AS total,
                    c.neto_no_grav, c.exento, c.imp_interno,
                    c.tipo_cambio, mo.codigo_afip AS moneda_codigo,
                    (SELECT COUNT(*) FROM compra_discriminaciones cd WHERE cd.compra_id = c.id) AS cant_alic,
                    (SELECT COALESCE(SUM(cd.cf_computable), 0) FROM compra_discriminaciones cd
                      WHERE cd.compra_id = c.id) AS cf_computable,
                    ' . $this->percCompra('tr.tipo_rg3685 = 1') . ' AS perc_iva,
                    ' . $this->percCompra('tr.tipo_rg3685 IN (2, 5)') . ' AS perc_nac,
                    ' . $this->percCompra('tr.tipo_rg3685 = 3') . ' AS perc_iibb,
                    ' . $this->percCompra('tr.tipo_rg3685 = 4') . ' AS perc_muni
               FROM compras c
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
               LEFT JOIN tipos_moneda      mo ON c.tipo_moneda_id       = mo.id
              WHERE c.periodo_id = ?
              ORDER BY c.fecha, c.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> Renglón por alícuota de cada compra del período. */
    public function comprasAlicuotas(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tc.codigo AS cbte_codigo, c.letra, c.punto_venta, c.numero, c.cuit,
                    cd.neto_gravado, cd.iva_alicuota, cd.iva_importe
               FROM compra_discriminaciones cd
               JOIN compras c ON c.id = cd.compra_id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ?
              ORDER BY c.fecha, c.id, cd.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
