<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Datos para la exportación SIFERE Convenio Multilateral V4: percepciones de IIBB
 * sufridas en compras de una jurisdicción (provincia) en un período.
 */
class SifereRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Percepciones de IIBB (tipo_rg3685 = 3) de compras del período cuya jurisdicción
     * (provincia de la percepción, o de su tipo de retención) es la indicada. Ordenadas
     * por proveedor, fecha y número (como el TXT del legacy).
     *
     * @return list<array<string, mixed>>
     */
    public function percepcionesIibb(int $periodoId, int $provinciaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.fecha, c.punto_venta, c.numero, c.cuit,
                    tc.codigo AS cbte_codigo, cp.importe
               FROM compra_percepciones cp
               JOIN compras c            ON cp.compra_id = c.id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
               LEFT JOIN tipos_retencion tr   ON cp.tipo_retencion_id  = tr.id
              WHERE c.periodo_id = ?
                AND tr.tipo_rg3685 = 3
                AND COALESCE(cp.provincia_id, tr.provincia_id) = ?
              ORDER BY c.cuit, c.fecha, c.numero'
        );
        $stmt->execute([$periodoId, $provinciaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Nombre y código de jurisdicción COMARB de la provincia (columna `jurisdiccion`,
     * sembrada desde el legacy). Devuelve null si la provincia no existe.
     *
     * @return array{nombre: string, jurisdiccion: ?string}|null
     */
    public function provincia(int $provinciaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT nombre, jurisdiccion FROM provincias WHERE id = ?');
        $stmt->execute([$provinciaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'nombre'       => (string) $row['nombre'],
            'jurisdiccion' => $row['jurisdiccion'] !== null ? (string) $row['jurisdiccion'] : null,
        ];
    }
}
