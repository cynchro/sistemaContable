<?php

namespace App\Modules\ApiKeys\Services;

use App\Support\Auth\ApiKeyManager;
use App\Modules\ApiKeys\Repositories\ApiKeyRepository;

class ApiKeyService
{
    public function __construct(
        private ApiKeyManager $manager,
        private ApiKeyRepository $repository
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $tenantId): array
    {
        return $this->repository->allForTenant($tenantId);
    }

    /** @return array<string, mixed> */
    public function get(string $id, string $tenantId): array
    {
        return $this->repository->findForTenant($id, $tenantId);
    }

    /**
     * Emite una key y devuelve el token en claro (visible una sola vez) junto a
     * la metadata persistida.
     *
     * @param  array<int, mixed> $scopes
     * @return array{token: string, key: array<string, mixed>}
     */
    public function create(string $tenantId, string $name, array $scopes = []): array
    {
        /** @var list<string> $clean */
        $clean  = array_values(array_filter($scopes, 'is_string'));
        $issued = $this->manager->issue($tenantId, $name, $clean);

        return [
            'token' => $issued['token'],
            'key'   => $this->repository->findForTenant($issued['id'], $tenantId),
        ];
    }

    /** Revoca una key del tenant. Lanza NotFoundException si no le pertenece. */
    public function revoke(string $id, string $tenantId): void
    {
        $this->repository->findForTenant($id, $tenantId); // valida pertenencia/existencia
        $this->manager->revoke($id);
    }
}
