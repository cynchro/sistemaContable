<?php

namespace Tests\Unit\Modules\Auth;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Repositories\AuthRepository;
use App\Exceptions\AuthException;
use App\Support\RateLimiter;
use App\Support\Auth\PermissionChecker;
use Tests\Unit\UnitTestCase;

class ImpersonateTest extends UnitTestCase
{
    private AuthService $service;
    private AuthRepository $repository;
    private PermissionChecker $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository  = $this->createMock(AuthRepository::class);
        $this->permissions = $this->createMock(PermissionChecker::class);
        $this->service     = new AuthService($this->repository, new RateLimiter(), $this->permissions);
    }

    public function test_impersonate_throws_when_admin_not_found(): void
    {
        $this->repository->method('findUserById')->willReturn(null);

        $this->expectException(AuthException::class);
        $this->service->impersonate(1, 2, 'tenant-a');
    }

    public function test_impersonate_throws_when_admin_lacks_role(): void
    {
        $this->repository
            ->method('findUserById')
            ->willReturn(['id' => 1, 'rol' => 2, 'tenant_id' => 'tenant-a']);

        $this->expectException(AuthException::class);
        $this->service->impersonate(1, 2, 'tenant-a');
    }

    public function test_impersonate_throws_when_target_is_different_tenant(): void
    {
        $this->repository
            ->method('findUserById')
            ->willReturnOnConsecutiveCalls(
                ['id' => 1, 'rol' => 1, 'tenant_id' => 'tenant-a'],
                ['id' => 2, 'rol' => 2, 'tenant_id' => 'tenant-b'],
            );
        $this->permissions->method('allows')->willReturn(true);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('No puedes suplantar a un usuario de otro tenant.');
        $this->service->impersonate(1, 2, 'tenant-a');
    }

    public function test_impersonate_throws_when_target_not_found(): void
    {
        $this->repository
            ->method('findUserById')
            ->willReturnOnConsecutiveCalls(
                ['id' => 1, 'rol' => 1, 'tenant_id' => 'tenant-a'],
                null,
            );
        $this->permissions->method('allows')->willReturn(true);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('El usuario objetivo no existe.');
        $this->service->impersonate(1, 2, 'tenant-a');
    }

    public function test_impersonate_skips_tenant_check_when_no_tenant_id_provided(): void
    {
        $this->repository
            ->method('findUserById')
            ->willReturnOnConsecutiveCalls(
                ['id' => 1, 'rol' => 1, 'tenant_id' => 'tenant-a'],
                ['id' => 2, 'rol' => 2, 'tenant_id' => 'tenant-b'],
            );
        $this->permissions->method('allows')->willReturn(true);

        $this->repository->method('updateToken');

        // Passing null skips the tenant check (backward-compat, internal use)
        $token = $this->service->impersonate(1, 2, null);
        $this->assertIsString($token);
    }
}
