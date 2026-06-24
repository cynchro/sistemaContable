<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Persistencia de los formatos de exportación TXT configurables, por tenant
 * (cabecera en `iva_export_formatos` + campos en `iva_export_formato_campos`).
 * La pertenencia al tenant la garantiza el filtro por `tenant_id`.
 */
class ExportFormatoRepository
{
    /** Columnas de cada campo del formato (orden de exportación incluido). */
    private const CAMPO_COLS = [
        'campo', 'orden', 'longitud', 'relleno', 'alinea', 'decimales', 'sep_decimal', 'formato_fecha',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> formatos del tenant (sin campos) */
    public function findAllByTenant(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, tipo, separador, eol FROM iva_export_formatos
              WHERE tenant_id = ? ORDER BY nombre'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null formato + sus campos ordenados */
    public function findById(int $id, string $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, tipo, separador, eol FROM iva_export_formatos
              WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$id, $tenantId]);
        $formato = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($formato === false) {
            return null;
        }

        $campos = $this->pdo->prepare(
            'SELECT ' . implode(', ', self::CAMPO_COLS) . '
               FROM iva_export_formato_campos WHERE formato_id = ? ORDER BY orden, id'
        );
        $campos->execute([$id]);
        $formato['campos'] = $campos->fetchAll(PDO::FETCH_ASSOC);

        return $formato;
    }

    /**
     * Inserta el formato y sus campos. NO maneja la transacción (la envuelve el Service).
     *
     * @param array{
     *   nombre: string, tipo: string, separador: string, eol: string,
     *   campos: list<array<string, mixed>>
     * } $data
     */
    public function create(string $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_export_formatos (tenant_id, nombre, tipo, separador, eol)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $data['nombre'], $data['tipo'], $data['separador'], $data['eol']]);
        $formatoId = (int) $this->pdo->lastInsertId();

        $insertCampo = $this->pdo->prepare(
            'INSERT INTO iva_export_formato_campos (formato_id, ' . implode(', ', self::CAMPO_COLS) . ')
             VALUES (?, ' . implode(', ', array_fill(0, count(self::CAMPO_COLS), '?')) . ')'
        );

        foreach ($data['campos'] as $orden => $campo) {
            $insertCampo->execute([
                $formatoId,
                $campo['campo'],
                $campo['orden'] ?? $orden + 1,
                $campo['longitud'] ?? 0,
                $campo['relleno'] ?? ' ',
                $campo['alinea'] ?? 'izquierda',
                $campo['decimales'] ?? 2,
                $campo['sep_decimal'] ?? '.',
                $campo['formato_fecha'] ?? 'Y-m-d',
            ]);
        }

        return $formatoId;
    }

    public function delete(int $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_export_formatos WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
