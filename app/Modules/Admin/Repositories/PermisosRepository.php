<?php

namespace App\Modules\Admin\Repositories;

use PDO;
use PDOException;

class PermisosRepository
{
    private const ESTADO_INACTIVO = 0;
    private const ESTADO_ASIGNADO = 2;

    public function __construct(private PDO $pdo)
    {
    }

    public function find(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permisos');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permisos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAvailable(int $rolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id AS id_permiso, p.key
             FROM permisos p
             LEFT JOIN roles_permisos rp ON p.id = rp.permiso AND rp.rol = ?
             WHERE rp.permiso IS NULL'
        );
        $stmt->execute([$rolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInUse(int $rolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id AS id_permiso, p.key
             FROM permisos p
             INNER JOIN roles_permisos rp ON p.id = rp.permiso
             WHERE rp.rol = ?'
        );
        $stmt->execute([$rolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<int> $permisoIds */
    public function asignarBatch(int $rolId, array $permisoIds): void
    {
        if (empty($permisoIds)) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO roles_permisos (rol, permiso, estado) VALUES (?, ?, ?)'
            );
            foreach ($permisoIds as $permisoId) {
                $stmt->execute([$rolId, $permisoId, self::ESTADO_ASIGNADO]);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param list<int> $permisoIds */
    public function desasignarBatch(int $rolId, array $permisoIds): void
    {
        if (empty($permisoIds)) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM roles_permisos WHERE rol = ? AND permiso = ?'
            );
            foreach ($permisoIds as $permisoId) {
                $stmt->execute([$rolId, $permisoId]);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createPermiso(string $key, string $descripcion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO permisos (`key`, descripcion, estado) VALUES (?, ?, ?)'
        );
        $stmt->execute([$key, $descripcion, self::ESTADO_INACTIVO]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePermiso(int $id, string $key, string $descripcion, int $estado): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE permisos SET `key` = ?, descripcion = ?, estado = ? WHERE id = ?'
        );
        $stmt->execute([$key, $descripcion, $estado, $id]);
        return $stmt->rowCount() > 0;
    }
}
