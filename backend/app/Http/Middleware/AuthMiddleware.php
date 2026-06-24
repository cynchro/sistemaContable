<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Auth\JwtGuard;
use App\Support\Auth\ApiKeyGuard;
use App\Exceptions\AuthException;
use App\Support\Contracts\GuardInterface;
use App\Support\Contracts\MiddlewareInterface;

/**
 * Autentica la petición probando guards en orden: JWT de usuario (app propia)
 * y luego API key (terceros). El primero que reconoce la credencial gana.
 *
 * Setea el Principal en la petición y, por retrocompatibilidad, también el
 * "user array" que leen TenantMiddleware y PermissionMiddleware.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /** @var list<GuardInterface> */
    private array $guards;

    public function __construct(JwtGuard $jwtGuard, ApiKeyGuard $apiKeyGuard)
    {
        $this->guards = [$jwtGuard, $apiKeyGuard];
    }

    public function handle(Request $request, callable $next): Response
    {
        foreach ($this->guards as $guard) {
            $principal = $guard->authenticate($request);

            if ($principal !== null) {
                $request->setPrincipal($principal);
                $request->setUser($principal->claims);

                return $next($request);
            }
        }

        throw new AuthException('Token not provided.');
    }
}
