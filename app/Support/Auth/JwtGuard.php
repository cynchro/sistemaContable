<?php

namespace App\Support\Auth;

use PDO;
use App\Support\Request;
use App\Support\JWTConfig;
use App\Exceptions\AuthException;
use App\Support\Contracts\GuardInterface;

/**
 * Guard del esquema propio de la app: JWT de usuario con revocación por tabla.
 * Preserva el comportamiento histórico de AuthMiddleware.
 */
final class JwtGuard implements GuardInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function authenticate(Request $request): ?Principal
    {
        $token = $request->bearerToken();

        // No es nuestro esquema: sin bearer, o es una API key (mk_*).
        if ($token === null || str_starts_with($token, ApiKeyManager::TOKEN_PREFIX)) {
            return null;
        }

        $payload = JWTConfig::decodeToken($token);

        if (!$payload) {
            throw new AuthException('Invalid or expired token.');
        }

        $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE token = ?');
        $stmt->execute([$token]);

        if (!$stmt->fetch()) {
            throw new AuthException('Token has been revoked.');
        }

        return new Principal(
            type: 'user',
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            userId: isset($payload['sub']) ? (int) $payload['sub'] : null,
            scopes: ['*'], // la app propia tiene acceso total; el RBAC sigue gobernando vía PermissionMiddleware
            rol: isset($payload['rol']) ? (int) $payload['rol'] : null,
            claims: $payload,
        );
    }
}
