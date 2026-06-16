<?php

namespace App\Modules\Tareas\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de tareas (por tenant) + comentarios e historial de estados.
 */
class TareaRepository
{
    private const WRITABLE = [
        'tipo_id', 'empresa_id', 'titulo', 'descripcion', 'estado', 'prioridad',
        'fecha_limite', 'created_by', 'assigned_to',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAll(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tareas WHERE tenant_id = ? ORDER BY fecha_limite IS NULL, fecha_limite, id'
        );
        $stmt->execute([$tenantId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tareas WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Tarea', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, string $tenantId): array
    {
        $fields = $this->writableFrom($data);
        $fields['tenant_id'] = $tenantId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $this->pdo->prepare(sprintf(
            'INSERT INTO tareas (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        ))->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $tenantId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, string $tenantId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']        = $id;
        $fields['tenant_id'] = $tenantId;

        $stmt = $this->pdo->prepare("UPDATE tareas SET {$set} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tareas WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    // ── Comentarios ───────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function comentarios(int $tareaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tarea_comentarios WHERE tarea_id = ? ORDER BY created_at, id');
        $stmt->execute([$tareaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addComentario(int $tareaId, ?int $usuarioId, string $comentario): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tarea_comentarios (tarea_id, usuario_id, comentario) VALUES (?, ?, ?)'
        );
        $stmt->execute([$tareaId, $usuarioId, $comentario]);
    }

    // ── Historial de estados ────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function historial(int $tareaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tarea_estado_historial WHERE tarea_id = ? ORDER BY created_at, id');
        $stmt->execute([$tareaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addHistorial(int $tareaId, ?string $anterior, string $nuevo, ?int $usuarioId, ?string $obs): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tarea_estado_historial (tarea_id, estado_anterior, estado_nuevo, usuario_id, observacion)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tareaId, $anterior, $nuevo, $usuarioId, $obs]);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableFrom(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITABLE));
    }
}
