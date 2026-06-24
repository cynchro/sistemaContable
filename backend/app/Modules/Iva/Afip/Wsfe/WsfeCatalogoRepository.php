<?php

namespace App\Modules\Iva\Afip\Wsfe;

use PDO;

/**
 * Lee los códigos de catálogo (en sus codificaciones AFIP) que necesita el armado del
 * comprobante electrónico, resolviendo los FKs de una venta en una sola consulta.
 */
class WsfeCatalogoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{tipo_codigo:?string, doc_tipo:?string, condicion_codigo:?string, moneda:?string}
     */
    public function codigosDeVenta(int $ventaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tc.codigo      AS tipo_codigo,
                    td.cod_afip    AS doc_tipo,
                    ci.codigo      AS condicion_codigo,
                    tm.codigo_afip AS moneda
             FROM ventas v
             LEFT JOIN tipos_comprobante tc ON tc.id = v.tipo_comprobante_id
             LEFT JOIN tipos_documento   td ON td.id = v.tipo_documento_id
             LEFT JOIN condiciones_iva   ci ON ci.id = v.condicion_iva_id
             LEFT JOIN tipos_moneda      tm ON tm.id = v.tipo_moneda_id
             WHERE v.id = ?'
        );
        $stmt->execute([$ventaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'tipo_codigo'      => isset($row['tipo_codigo']) ? (string) $row['tipo_codigo'] : null,
            'doc_tipo'         => isset($row['doc_tipo']) ? (string) $row['doc_tipo'] : null,
            'condicion_codigo' => isset($row['condicion_codigo']) ? (string) $row['condicion_codigo'] : null,
            'moneda'           => isset($row['moneda']) ? (string) $row['moneda'] : null,
        ];
    }
}
