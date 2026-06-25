<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Agregación de las operaciones del período para la DJ IVA Simple (apertura de otros
 * conceptos). Separa por signo del comprobante: signo > 0 alimenta Débito/Crédito
 * Fiscal; signo < 0 (notas de crédito) alimenta las Restituciones. Acotado a
 * `periodo_id` (la pertenencia la valida el Service).
 *
 * Lo gravado de ventas se agrupa por condición de IVA del receptor + alícuota (el
 * writer lo reagrupa por tipo de sujeto). Lo exento/no gravado se suma a nivel
 * comprobante. Compras: por alícuota, con crédito fiscal facturado y computable.
 */
class DjIvaSimpleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Ventas gravadas (neto + IVA) por condición de IVA y alícuota, del lado pedido.
     *
     * @return list<array{condicion_iva_id: ?int, alicuota: ?string, neto: string, iva: string}>
     */
    public function ventasGravado(int $periodoId, int $signo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.condicion_iva_id AS condicion_iva_id, vd.iva_alicuota AS alicuota,
                    SUM(vd.neto_gravado) AS neto, SUM(vd.iva_importe) AS iva
               FROM venta_discriminaciones vd
               JOIN ventas v ON vd.venta_id = v.id
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ? AND COALESCE(tc.signo, 1) ' . ($signo < 0 ? '<' : '>') . ' 0
                    AND vd.neto_gravado <> 0
              GROUP BY v.condicion_iva_id, vd.iva_alicuota'
        );
        $stmt->execute([$periodoId]);

        $out = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'condicion_iva_id' => $r['condicion_iva_id'] !== null ? (int) $r['condicion_iva_id'] : null,
                'alicuota'         => (string) ($r['alicuota'] ?? '0'),
                'neto'             => (string) ($r['neto'] ?? '0'),
                'iva'              => (string) ($r['iva'] ?? '0'),
            ];
        }

        return $out;
    }

    /** Total exento + no gravado de ventas (a nivel comprobante), del lado pedido. */
    public function ventasNoGravado(int $periodoId, int $signo): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT SUM(v.exento + v.neto_no_grav) AS monto
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ? AND COALESCE(tc.signo, 1) ' . ($signo < 0 ? '<' : '>') . ' 0'
        );
        $stmt->execute([$periodoId]);

        return (string) ($stmt->fetchColumn() ?: '0');
    }

    /**
     * Compras gravadas (neto + IVA facturado + crédito fiscal computable) por alícuota.
     *
     * @return list<array{alicuota: ?string, neto: string, iva: string, cf: string}>
     */
    public function comprasGravado(int $periodoId, int $signo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cd.iva_alicuota AS alicuota, SUM(cd.neto_gravado) AS neto,
                    SUM(cd.iva_importe) AS iva, SUM(cd.cf_computable) AS cf
               FROM compra_discriminaciones cd
               JOIN compras c ON cd.compra_id = c.id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ? AND COALESCE(tc.signo, 1) ' . ($signo < 0 ? '<' : '>') . ' 0
                    AND cd.neto_gravado <> 0
              GROUP BY cd.iva_alicuota'
        );
        $stmt->execute([$periodoId]);

        $out = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'alicuota' => (string) ($r['alicuota'] ?? '0'),
                'neto'     => (string) ($r['neto'] ?? '0'),
                'iva'      => (string) ($r['iva'] ?? '0'),
                'cf'       => (string) ($r['cf'] ?? '0'),
            ];
        }

        return $out;
    }
}
