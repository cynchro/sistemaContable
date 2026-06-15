<?php

namespace App\Modules\Usuario\Repositories;

use PDO;
use App\Helpers\PaginatorHelper;
use App\Exceptions\NotFoundException;

class UsuariosRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $page = 1, int $perPage = 10, ?string $tenantId = null): array
    {
        if ($tenantId !== null) {
            $paginator = new PaginatorHelper(
                $this->pdo,
                'SELECT * FROM usuarios WHERE tenant_id = ?',
                $page,
                $perPage,
                true,
                [$tenantId],
            );
        } else {
            $paginator = new PaginatorHelper($this->pdo, 'SELECT * FROM usuarios', $page, $perPage);
        }
        return $paginator->getPaginatedResults();
    }

    public function findById(int $id, ?string $tenantId = null): array
    {
        if ($tenantId !== null) {
            $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$id, $tenantId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new NotFoundException('Usuario', $id);
        }

        return $result;
    }

    public function updateSucursal(int $userId, int $sucursalId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE empleados SET id_sucursal = ? WHERE id_usuario = ?'
        );
        $stmt->execute([$sucursalId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, ?string $tenantId = null): bool
    {
        if ($tenantId !== null) {
            $stmt = $this->pdo->prepare('DELETE FROM usuarios WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$id, $tenantId]);
        } else {
            $stmt = $this->pdo->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
        }
        return $stmt->rowCount() > 0;
    }
}
