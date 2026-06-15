<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\UsuariosRepository;

class UsuariosService
{
    public function __construct(private UsuariosRepository $repository)
    {
    }

    /** @return array<string, mixed> */
    public function getAll(int $page = 1, int $perPage = 10, ?string $tenantId = null): array
    {
        return $this->repository->find($page, $perPage, $tenantId);
    }
}
