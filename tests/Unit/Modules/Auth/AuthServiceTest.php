<?php

namespace Tests\Unit\Modules\Auth;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Repositories\AuthRepository;
use App\Exceptions\AuthException;
use App\Support\RateLimiter;
use App\Support\Auth\PermissionChecker;
use Tests\Unit\UnitTestCase;

class AuthServiceTest extends UnitTestCase
{
    private AuthService $service;
    private AuthRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuthRepository::class);
        $this->service    = new AuthService(
            $this->repository,
            new RateLimiter(),
            $this->createMock(PermissionChecker::class)
        );
    }

    public function test_login_throws_auth_exception_when_user_not_found(): void
    {
        $this->repository
            ->method('findUserByName')
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->service->login(['usuario' => 'x@x.com', 'clave' => 'secret']);
    }

    public function test_login_throws_auth_exception_on_wrong_password(): void
    {
        $this->repository
            ->method('findUserByName')
            ->willReturn([
                'id'    => 1,
                'clave' => password_hash('correct', PASSWORD_BCRYPT),
                'rol'   => 2,
            ]);

        $this->expectException(AuthException::class);
        $this->service->login(['usuario' => 'x@x.com', 'clave' => 'wrong']);
    }

    public function test_logout_throws_if_token_not_found(): void
    {
        $this->repository
            ->method('clearToken')
            ->willReturn(false);

        $this->expectException(AuthException::class);
        $this->service->logout('invalid-token');
    }

    public function test_me_throws_if_token_not_found(): void
    {
        $this->repository
            ->method('findUserByToken')
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->service->me('invalid-token');
    }

    public function test_me_returns_user_for_valid_token(): void
    {
        $expected = ['id' => 1, 'usuario' => 'admin@test.com', 'rol' => 1];

        $this->repository
            ->method('findUserByToken')
            ->willReturn($expected);

        $user = $this->service->me('valid-token');
        $this->assertSame($expected, $user);
    }

    public function test_impersonate_throws_if_admin_not_found(): void
    {
        $this->repository
            ->method('findUserById')
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->service->impersonate(1, 2);
    }

    public function test_impersonate_throws_if_admin_has_no_admin_role(): void
    {
        $this->repository
            ->method('findUserById')
            ->willReturn(['id' => 1, 'rol' => 2]);

        $this->expectException(AuthException::class);
        $this->service->impersonate(1, 2);
    }
}
