<?php

namespace App\Http\Middleware;

use PDO;
use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;
use App\Exceptions\AuthException;

class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();

        if (!$user || empty($user['tenant_id'])) {
            throw new AuthException('Tenant context missing from token.', 401);
        }

        $tenantId = (string) $user['tenant_id'];

        $stmt = $this->pdo->prepare('SELECT id FROM tenants WHERE id = ?');
        $stmt->execute([$tenantId]);

        if (!$stmt->fetch()) {
            throw new AuthException('Tenant not found.', 401);
        }

        $request->setTenantId($tenantId);

        return $next($request);
    }
}
