<?php

namespace App\Modules\Fiscal\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de vencimientos (obligaciones fiscales) por empresa (contribuyente).
 * El campo `tributos` se guarda como JSON y se devuelve como arreglo.
 */
class VencimientoRepository
{
    private const WRITABLE = [
        'agencia', 'jurisdiccion', 'tributos', 'titulo', 'descripcion', 'fecha_vencimiento',
        'estado', 'observaciones', 'observaciones_estado', 'is_activo',
        'usuario_creador_id', 'usuario_actualizador_id',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM vencimientos WHERE empresa_id = ? ORDER BY fecha_vencimiento, id'
        );
        $stmt->execute([$empresaId]);

        return array_map([$this, 'decode'], (array) $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vencimientos WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Vencimiento', $id);
        }

        return $this->decode($row);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId): array
    {
        $fields = $this->writableFrom($data);
        $fields['empresa_id'] = $empresaId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO vencimientos (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $empresaId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, int $empresaId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']         = $id;
        $fields['empresa_id'] = $empresaId;

        $stmt = $this->pdo->prepare("UPDATE vencimientos SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM vencimientos WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Filtra a columnas escribibles y serializa `tributos` (array) a JSON.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableFrom(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(self::WRITABLE));

        if (isset($fields['tributos']) && is_array($fields['tributos'])) {
            $fields['tributos'] = json_encode(array_values($fields['tributos']));
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        if (isset($row['tributos']) && is_string($row['tributos'])) {
            $decoded = json_decode($row['tributos'], true);
            $row['tributos'] = is_array($decoded) ? $decoded : [];
        }

        return $row;
    }
}
