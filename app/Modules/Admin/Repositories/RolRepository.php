<?php

namespace App\Modules\Admin\Repositories;

use PDO;
use PDOException;
use App\Exceptions\NotFoundException;

class RolRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roles');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Rol', $id);
        }

        return $row;
    }

    private const ESTADO_ACTIVO = 1;

    public function create(string $nombre, ?int $parentId = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO roles (nombre, parent_id, estado) VALUES (?, ?, ?)');
        $stmt->execute([$nombre, $parentId, self::ESTADO_ACTIVO]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $nombre, int $estado, ?int $parentId = null): bool
    {
        $stmt = $this->pdo->prepare('UPDATE roles SET nombre = ?, parent_id = ?, estado = ? WHERE id = ?');
        $stmt->execute([$nombre, $parentId, $estado, $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * ¿Asignar a $rolId el padre $parentId crearía un ciclo en la jerarquía?
     *
     * Hay ciclo si $rolId es el propio $parentId o un ancestro suyo: en ese caso
     * la cadena de padres de $parentId ya pasa por $rolId, y enlazarlos la cierra.
     */
    public function wouldCreateCycle(int $rolId, int $parentId): bool
    {
        $stmt = $this->pdo->prepare(
            'WITH RECURSIVE chain AS (
                SELECT id, parent_id FROM roles WHERE id = ?
                UNION ALL
                SELECT r.id, r.parent_id FROM roles r
                JOIN chain c ON r.id = c.parent_id
            )
            SELECT 1 FROM chain WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$parentId, $rolId]);

        return (bool) $stmt->fetch();
    }
}
