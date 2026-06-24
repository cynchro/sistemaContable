<?php

namespace App\Modules\Admin\Repositories;

use PDO;
use App\Helpers\PaginatorHelper;

/**
 * Lectura de usuarios para la administración (endpoint /admin/users).
 * Acotado al tenant del estudio que administra.
 */
class UsuariosRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
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
}
