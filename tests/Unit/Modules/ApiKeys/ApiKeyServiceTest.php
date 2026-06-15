<?php

namespace Tests\Unit\Modules\ApiKeys;

use App\Support\Auth\ApiKeyManager;
use App\Exceptions\NotFoundException;
use App\Modules\ApiKeys\Services\ApiKeyService;
use App\Modules\ApiKeys\Repositories\ApiKeyRepository;
use Tests\Unit\UnitTestCase;

class ApiKeyServiceTest extends UnitTestCase
{
    public function test_create_issues_token_and_returns_metadata(): void
    {
        $manager = $this->createMock(ApiKeyManager::class);
        $manager->expects($this->once())
            ->method('issue')
            ->with('tenant-1', 'mi key', ['clientes.read'])
            ->willReturn(['token' => 'mk_live_x_secret', 'id' => 'uuid-1', 'prefix' => 'mk_live_x']);

        $repo = $this->createMock(ApiKeyRepository::class);
        $repo->method('findForTenant')
            ->with('uuid-1', 'tenant-1')
            ->willReturn(['id' => 'uuid-1', 'name' => 'mi key', 'prefix' => 'mk_live_x']);

        $service = new ApiKeyService($manager, $repo);
        $result  = $service->create('tenant-1', 'mi key', ['clientes.read']);

        $this->assertSame('mk_live_x_secret', $result['token']);
        $this->assertSame('uuid-1', $result['key']['id']);
    }

    public function test_create_drops_non_string_scopes(): void
    {
        $manager = $this->createMock(ApiKeyManager::class);
        $manager->expects($this->once())
            ->method('issue')
            ->with('tenant-1', 'k', ['ia.rag']) // 42 y null filtrados
            ->willReturn(['token' => 't', 'id' => 'id', 'prefix' => 'p']);

        $repo = $this->createMock(ApiKeyRepository::class);
        $repo->method('findForTenant')->willReturn(['id' => 'id']);

        $service = new ApiKeyService($manager, $repo);
        $service->create('tenant-1', 'k', ['ia.rag', 42, null]);
    }

    public function test_revoke_checks_ownership_then_revokes(): void
    {
        $repo = $this->createMock(ApiKeyRepository::class);
        $repo->expects($this->once())
            ->method('findForTenant')
            ->with('uuid-1', 'tenant-1')
            ->willReturn(['id' => 'uuid-1']);

        $manager = $this->createMock(ApiKeyManager::class);
        $manager->expects($this->once())->method('revoke')->with('uuid-1');

        $service = new ApiKeyService($manager, $repo);
        $service->revoke('uuid-1', 'tenant-1');
    }

    public function test_revoke_throws_and_does_not_revoke_when_not_owned(): void
    {
        $repo = $this->createMock(ApiKeyRepository::class);
        $repo->method('findForTenant')
            ->willThrowException(new NotFoundException('ApiKey', 'uuid-x'));

        $manager = $this->createMock(ApiKeyManager::class);
        $manager->expects($this->never())->method('revoke');

        $service = new ApiKeyService($manager, $repo);

        $this->expectException(NotFoundException::class);
        $service->revoke('uuid-x', 'tenant-1');
    }
}
