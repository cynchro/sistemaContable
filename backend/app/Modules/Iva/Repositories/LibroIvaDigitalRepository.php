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

    /**
     * `CASE` de "Cantidad de alícuotas de IVA" (campo 19) + "Código de operación" (campo
     * 20), según el spec oficial de ARCA (`Libro-IVA-Digital-Especificaciones.pdf`,
     * campos 19/20 de VENTAS_CBTE y COMPRAS_CBTE, texto idéntico en ambos):
     * - Comprobante que NO discrimina IVA por diseño (letra B/C): cant_alic FIJO en '0',
     *   código de operación en blanco. Nunca lleva línea en `_ALICUOTAS`.
     * - Comprobante con discriminación real (>=1 fila): cant_alic = esa cantidad, código
     *   de operación en blanco (caso normal).
     * - Comprobante que SÍ discrimina IVA por diseño (A/M/...) pero sin ninguna fila
     *   gravada, 100% Exento o No Gravado: "se consignará '1' [...] como también si se
     *   trata de una operación de venta/compra de productos exentos con productos
     *   gravados a tasa única" (campo 19) + código de operación 'E'/'N' (campo 20) — Y
     *   una línea en `_ALICUOTAS` con alícuota 0% ("La alícuota podrá ser cero en caso de
     *   operaciones de... exentas y no gravadas, procediéndose a completar el campo
     *   'Código de operación'"). Sin esto ARCA rechaza el `_CBTE` con "es obligatorio
     *   informar alícuotas IVA" (encontrado en vivo 24/08/2026, comprobante real RED
     *   COLON S.A., Factura A 100% Exento) — la hipótesis previa de un `LEFT JOIN` con
     *   línea "0%" forzada fallaba porque `cant_alic` seguía calculado por `COUNT(*)`
     *   (=0) en vez de forzarse a 1, desincronizado contra la línea que sí se emitía.
     */
    private const CANT_ALIC_SQL = "CASE
        WHEN %1\$s.letra IN ('B', 'C') THEN 0
        WHEN (SELECT COUNT(*) FROM %2\$s WHERE %3\$s = %1\$s.id) > 0
            THEN (SELECT COUNT(*) FROM %2\$s WHERE %3\$s = %1\$s.id)
        ELSE 1
    END";

    private const CODIGO_OPERACION_SQL = "CASE
        WHEN %1\$s.letra IN ('B', 'C') THEN ''
        WHEN (SELECT COUNT(*) FROM %2\$s WHERE %3\$s = %1\$s.id) > 0 THEN ''
        WHEN COALESCE(%1\$s.exento, 0) != 0 THEN 'E'
        WHEN COALESCE(%1\$s.neto_no_grav, 0) != 0 THEN 'N'
        ELSE 'A'
    END";

    /**
     * Cabeceras de ventas del período. Excluye comprobantes con total $0 (neto, exento,
     * no gravado e imp. interno todos en cero) — encontrado en vivo (24/08/2026) con
     * Notas de Crédito reales traídas desde ARCA (anulaciones técnicas sin valor
     * económico, ej. COSENA SEGUROS S.A.): ARCA rechaza el Libro IVA Digital si un
     * comprobante así aparece en el archivo `_CBTE`, sea cual sea la alícuota que se le
     * asigne en `_ALICUOTAS` — no aportan nada a la declaración, no tiene sentido
     * exportarlos.
     *
     * @return list<array<string, mixed>>
     */
    public function ventasCbte(int $periodoId): array
    {
        $cantAlic = sprintf(self::CANT_ALIC_SQL, 'v', 'venta_discriminaciones', 'venta_id');
        $codigoOp = sprintf(self::CODIGO_OPERACION_SQL, 'v', 'venta_discriminaciones', 'venta_id');
        $stmt = $this->pdo->prepare(
            'SELECT v.fecha, tc.codigo AS cbte_codigo, tc.cod_citi, v.letra, v.punto_venta, v.numero, v.numero_fin,
                    td.cod_afip AS doc_cod_afip, v.cuit, v.cliente_nombre,
                    COALESCE(v.total_informado, v.total) AS total, v.neto_no_grav,
                    v.exento, v.imp_interno, v.tipo_cambio, mo.codigo_afip AS moneda_codigo,
                    ' . $cantAlic . ' AS cant_alic,
                    ' . $codigoOp . ' AS codigo_operacion,
                    ' . $this->percVenta('tr.tipo_rg3685 = 5') . ' AS perc_no_cat,
                    ' . $this->percVenta('tr.tipo_rg3685 IN (1, 2)') . ' AS perc_nac,
                    ' . $this->percVenta('tr.tipo_rg3685 = 3') . ' AS perc_iibb,
                    ' . $this->percVenta('tr.tipo_rg3685 = 4') . ' AS perc_muni
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
               LEFT JOIN tipos_documento   td ON v.tipo_documento_id   = td.id
               LEFT JOIN tipos_moneda      mo ON v.tipo_moneda_id       = mo.id
              WHERE v.periodo_id = ? AND COALESCE(v.total_informado, v.total) != 0
              ORDER BY v.fecha, v.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renglón por alícuota de cada venta del período: una fila por cada
     * `venta_discriminaciones` real, MÁS una fila sintética "0%" por cada comprobante que
     * discrimina IVA por diseño (letra fuera de B/C) pero no tiene ninguna línea gravada
     * y sí tiene Exento o No Gravado > 0 — ver `CANT_ALIC_SQL`/`CODIGO_OPERACION_SQL` para
     * el porqué (spec oficial de ARCA, campos 19/20). Un comprobante letra B/C, o uno sin
     * nada de nada (ya excluido por `total != 0` en `ventasCbte`), no genera ninguna fila.
     *
     * @return list<array<string, mixed>>
     */
    public function ventasAlicuotas(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT tc.codigo AS cbte_codigo, tc.cod_citi, v.letra, v.punto_venta, v.numero, v.fecha, v.id AS orden_id,
                    vd.neto_gravado, vd.iva_alicuota, vd.iva_importe
               FROM venta_discriminaciones vd
               JOIN ventas v ON v.id = vd.venta_id
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ?

             UNION ALL

             SELECT tc.codigo AS cbte_codigo, tc.cod_citi, v.letra, v.punto_venta, v.numero, v.fecha, v.id AS orden_id,
                    '0' AS neto_gravado, '0' AS iva_alicuota, '0' AS iva_importe
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ?
                AND COALESCE(v.total_informado, v.total) != 0
                AND v.letra NOT IN ('B', 'C')
                AND (COALESCE(v.exento, 0) != 0 OR COALESCE(v.neto_no_grav, 0) != 0)
                AND NOT EXISTS (SELECT 1 FROM venta_discriminaciones vd2 WHERE vd2.venta_id = v.id)

              ORDER BY fecha, orden_id"
        );
        $stmt->execute([$periodoId, $periodoId]);

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
        $cantAlic = sprintf(self::CANT_ALIC_SQL, 'c', 'compra_discriminaciones', 'compra_id');
        $codigoOp = sprintf(self::CODIGO_OPERACION_SQL, 'c', 'compra_discriminaciones', 'compra_id');
        $stmt = $this->pdo->prepare(
            'SELECT c.fecha, tc.codigo AS cbte_codigo, tc.cod_citi, c.letra, c.punto_venta, c.numero,
                    c.cuit, c.proveedor_nombre, COALESCE(c.total_informado, c.total) AS total,
                    c.neto_no_grav, c.exento, c.imp_interno,
                    c.tipo_cambio, mo.codigo_afip AS moneda_codigo,
                    ' . $cantAlic . ' AS cant_alic,
                    ' . $codigoOp . ' AS codigo_operacion,
                    (SELECT COALESCE(SUM(cd.cf_computable), 0) FROM compra_discriminaciones cd
                      WHERE cd.compra_id = c.id) AS cf_computable,
                    ' . $this->percCompra('tr.tipo_rg3685 = 1') . ' AS perc_iva,
                    ' . $this->percCompra('tr.tipo_rg3685 IN (2, 5)') . ' AS perc_nac,
                    ' . $this->percCompra('tr.tipo_rg3685 = 3') . ' AS perc_iibb,
                    ' . $this->percCompra('tr.tipo_rg3685 = 4') . ' AS perc_muni
               FROM compras c
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
               LEFT JOIN tipos_moneda      mo ON c.tipo_moneda_id       = mo.id
              WHERE c.periodo_id = ? AND COALESCE(c.total_informado, c.total) != 0
              ORDER BY c.fecha, c.id'
        );
        $stmt->execute([$periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renglón por alícuota de cada compra del período: una fila por cada
     * `compra_discriminaciones` real, MÁS una fila sintética "0%" por comprobante que
     * discrimina IVA por diseño (letra fuera de B/C) pero no tiene ninguna línea gravada
     * y sí tiene Exento o No Gravado > 0 — mismo mecanismo que `ventasAlicuotas` (ver
     * `CANT_ALIC_SQL`/`CODIGO_OPERACION_SQL`).
     *
     * ⚠️ RESUELTO (25/08/2026, spec oficial descargado de
     * afip.gob.ar/iva/documentos/Libro-IVA-Digital-Especificaciones.pdf, campos 19/20 de
     * COMPRAS_CBTE): el caso RED COLON S.A. (Factura A 100% Exento) fallaba porque
     * `cant_alic` seguía en `COUNT(*)=0` mientras se intentaba forzar una línea "0%" — el
     * spec exige AMBAS cosas juntas: `cant_alic=1` + `Código de operación='E'` en el CBTE,
     * y la línea con alícuota 0% en el ALICUOTAS. Cita textual: "[cant. alícuotas] se
     * consignará '1' [...] como también si se trata de una operación de compra de
     * productos exentos con productos gravados a tasa única" / "[Código de operación] Si
     * la alícuota de IVA es igual a cero, este campo se deberá completar [...] E-
     * Operaciones Exentas [...] N- No gravado".
     *
     * @return list<array<string, mixed>>
     */
    public function comprasAlicuotas(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT tc.codigo AS cbte_codigo, tc.cod_citi, c.letra, c.punto_venta, c.numero, c.cuit,
                    c.fecha, c.id AS orden_id, cd.neto_gravado, cd.iva_alicuota, cd.iva_importe
               FROM compra_discriminaciones cd
               JOIN compras c ON c.id = cd.compra_id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ?

             UNION ALL

             SELECT tc.codigo AS cbte_codigo, tc.cod_citi, c.letra, c.punto_venta, c.numero, c.cuit,
                    c.fecha, c.id AS orden_id, '0' AS neto_gravado, '0' AS iva_alicuota, '0' AS iva_importe
               FROM compras c
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ?
                AND COALESCE(c.total_informado, c.total) != 0
                AND c.letra NOT IN ('B', 'C')
                AND (COALESCE(c.exento, 0) != 0 OR COALESCE(c.neto_no_grav, 0) != 0)
                AND NOT EXISTS (SELECT 1 FROM compra_discriminaciones cd2 WHERE cd2.compra_id = c.id)

              ORDER BY fecha, orden_id"
        );
        $stmt->execute([$periodoId, $periodoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
