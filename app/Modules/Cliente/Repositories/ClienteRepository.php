<?php

namespace App\Modules\Cliente\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

class ClienteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAll(string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clientes WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clientes WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Cliente', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     *
     * Scaffolding: inserta la columna de ejemplo `nombre`. Para tu dominio,
     * amplía las columnas aquí, en la migración y en CreateClienteRequest.
     */
    public function create(array $data, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO clientes (nombre, tenant_id) VALUES (?, ?)');
        $stmt->execute([$data['nombre'], $tenantId]);

        return $this->findById((int) $this->pdo->lastInsertId(), $tenantId);
    }

    /**
     * @param array<string, mixed> $data
     *
     * Scaffolding: actualiza la columna de ejemplo `nombre`, siempre filtrando
     * por `tenant_id` (aislamiento row-level). Devuelve false si no hubo cambios.
     */
    public function update(int $id, array $data, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE clientes SET nombre = ? WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$data['nombre'], $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM clientes WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}
