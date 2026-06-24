<?php

namespace App\Support\Auth;

use App\Support\Request;
use App\Exceptions\AuthException;
use App\Support\Contracts\GuardInterface;

/**
 * Guard de terceros: autentica vía API key en `Authorization: Bearer mk_...`
 * o en el header `X-Api-Key`.
 */
final class ApiKeyGuard implements GuardInterface
{
    public function __construct(private ApiKeyManager $keys)
    {
    }

    public function authenticate(Request $request): ?Principal
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return null; // no es nuestro esquema
        }

        $row = $this->keys->verify($token);

        if ($row === null) {
            throw new AuthException('Invalid API key.');
        }

        $this->keys->touch((string) $row['id']);

        /** @var list<string> $scopes */
        $scopes = is_array($row['scopes']) ? $row['scopes'] : [];

        return new Principal(
            type: 'api_key',
            tenantId: (string) $row['tenant_id'],
            userId: null,
            scopes: $scopes,
            rol: null,
            claims: [
                'tenant_id'  => (string) $row['tenant_id'],
                'api_key_id' => (string) $row['id'],
            ],
        );
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if ($bearer !== null && str_starts_with($bearer, ApiKeyManager::TOKEN_PREFIX)) {
            return $bearer;
        }

        $header = $request->header('X-Api-Key');
        if ($header !== null && str_starts_with($header, ApiKeyManager::TOKEN_PREFIX)) {
            return $header;
        }

        return null;
    }
}
