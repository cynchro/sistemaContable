<?php

namespace App\Modules\Auth\Services;

use App\Exceptions\AuthException;
use App\Exceptions\RateLimitException;
use App\Support\JWTConfig;
use App\Support\RateLimiter;
use App\Support\Roles;
use App\Support\Auth\PermissionChecker;
use App\Modules\Auth\Repositories\AuthRepository;

class AuthService
{
    public function __construct(
        private AuthRepository $repository,
        private RateLimiter $rateLimiter,
        private PermissionChecker $permissions
    ) {
    }

    public function register(array $data): array
    {
        $hashed = password_hash($data['clave'], PASSWORD_BCRYPT, ['cost' => 12]);
        return $this->repository->create($data['usuario'], $hashed, (int) ($data['rol'] ?? 0));
    }

    /** @return array{access_token: string, refresh_token: string} */
    public function login(array $data): array
    {
        $username = $data['usuario'];
        $cacheKey = 'login_attempt:' . $username;

        if ($this->rateLimiter->tooManyAttempts($cacheKey)) {
            throw new RateLimitException('Too many login attempts. Please try again in 5 minutes.');
        }

        $user = $this->repository->findUserByName($username);

        if (!$user || !password_verify($data['clave'], $user['clave'])) {
            $this->rateLimiter->hit($cacheKey);
            throw new AuthException('Invalid credentials.', 401);
        }

        $this->rateLimiter->clear($cacheKey);

        $userId       = (int) $user['id'];
        $accessToken  = JWTConfig::generateToken($userId, $user['tenant_id'] ?? null);
        $refreshToken = JWTConfig::generateRefreshToken();

        $this->repository->updateToken($userId, $accessToken);
        $this->repository->deleteAllRefreshTokens($userId);
        $this->repository->saveRefreshToken($userId, $refreshToken, JWTConfig::refreshExpiresAt());

        return ['access_token' => $accessToken, 'refresh_token' => $refreshToken];
    }

    /** @return array{access_token: string, refresh_token: string} */
    public function refreshTokens(string $refreshToken): array
    {
        $stored = $this->repository->findRefreshToken($refreshToken);

        if (!$stored) {
            throw new AuthException('Invalid or expired refresh token.', 401);
        }

        $userId = (int) $stored['user_id'];
        $user   = $this->repository->findUserById($userId);

        if (!$user) {
            throw new AuthException('User not found.', 401);
        }

        $newAccessToken  = JWTConfig::generateToken($userId, $user['tenant_id'] ?? null);
        $newRefreshToken = JWTConfig::generateRefreshToken();

        $this->repository->updateToken($userId, $newAccessToken);
        $this->repository->deleteRefreshToken($refreshToken);
        $this->repository->saveRefreshToken($userId, $newRefreshToken, JWTConfig::refreshExpiresAt());

        return ['access_token' => $newAccessToken, 'refresh_token' => $newRefreshToken];
    }

    public function logout(string $accessToken, ?string $refreshToken = null): void
    {
        $cleared = $this->repository->clearToken($accessToken);

        if (!$cleared) {
            throw new AuthException('Invalid token or already logged out.', 401);
        }

        if ($refreshToken !== null) {
            $this->repository->deleteRefreshToken($refreshToken);
        }
    }

    public function me(string $token): array
    {
        $user = $this->repository->findUserByToken($token);

        if (!$user) {
            throw new AuthException('Invalid token or user not found.', 401);
        }

        return $user;
    }

    public function permisos(string $token, string $key): array
    {
        return $this->repository->findUserPermissions($token, $key);
    }

    public function impersonate(int $adminId, int $targetId, ?string $adminTenantId = null): string
    {
        $admin = $this->repository->findUserById($adminId);

        if (!$admin || !$this->permissions->allows((int) ($admin['rol'] ?? 0), Roles::SUPER_PERMISSION)) {
            throw new AuthException('No tienes permisos para suplantar usuarios.', 403);
        }

        $target = $this->repository->findUserById($targetId);

        if (!$target) {
            throw new AuthException('El usuario objetivo no existe.', 404);
        }

        if ($adminTenantId !== null && ($target['tenant_id'] ?? null) !== $adminTenantId) {
            throw new AuthException('No puedes suplantar a un usuario de otro tenant.', 403);
        }

        $token = JWTConfig::generateToken($targetId, $target['tenant_id'] ?? null);
        $this->repository->updateToken($targetId, $token);

        return $token;
    }
}
