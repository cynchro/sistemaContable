<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Roles;
use App\Support\Auth\PermissionChecker;
use App\Support\Contracts\MiddlewareInterface;
use App\Modules\Compartido\Repositories\EmpresaLockRepository;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;

/**
 * Aplica el "modo ocupado" de una empresa a cualquier ruta anidada bajo `/empresas/{id}` o
 * `/empresas/{empresaId}/...` (WhatsApp con el cliente, 11/08/2026): mientras otro usuario tiene
 * la empresa ocupada (`EmpresaLockService::ocupar`, disparado al elegirla activa en el header),
 * un usuario NO-admin queda bloqueado del todo (ni lectura); un admin entra en modo observador
 * (lectura sí, escritura no).
 *
 * Deliberadamente NO se aplica a `/empresas/{id}/ocupar|ping|liberar` (esas rutas viven en un
 * grupo aparte en `Compartido/routes.php`): son las que MANEJAN el lock, no las que lo respetan
 * — si estuvieran acá, un admin nunca podría llamar a `/ocupar` para entrar en modo observador
 * (quedaría bloqueado como cualquier otra escritura).
 *
 * Tampoco se aplica a un principal de tipo API key (25/08/2026, encontrado en vivo probando el
 * botón "Liquidar IVA"): el candado de "empresa ocupada" existe para que dos CONTADORES humanos
 * no pisen la misma empresa a la vez — no tiene sentido aplicárselo a una integración/bot que
 * actúa async en nombre de un usuario que ya autorizó la acción de antemano (y que, en el caso
 * concreto del worker, típicamente sí tiene la empresa abierta en su propio navegador — bloquear
 * al bot ahí haría que el botón nunca funcionara). La API key ya está acotada por sus propios
 * scopes (`PermissionMiddleware`), que es el control de acceso que sí le corresponde.
 */
class EmpresaLockMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EmpresaLockRepository $locks,
        private PermissionChecker $checker,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $empresaId = $this->resolverEmpresaId($request);
        $user      = $request->user();
        $principal = $request->principal();

        if ($empresaId === null || !$user || ($principal !== null && $principal->isApiKey())) {
            return $next($request);
        }

        $usuarioId = (int) ($user['sub'] ?? 0);
        $estado    = $this->locks->estadoDe($empresaId, (string) $request->tenantId());

        if ($estado === null || (int) $estado['usuario_id'] === $usuarioId) {
            return $next($request);
        }

        $esAdmin = $this->checker->allows((int) ($user['rol'] ?? 0), Roles::SUPER_PERMISSION);
        if (!$esAdmin) {
            throw new ConflictException(
                "{$estado['usuario_nombre']} está trabajando en esta empresa ahora. Probá de nuevo en unos minutos."
            );
        }

        $esEscritura = !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        if ($esEscritura) {
            throw new ForbiddenException(
                "Modo observador: {$estado['usuario_nombre']} está trabajando en esta empresa. "
                . 'Podés ver, pero no modificar.'
            );
        }

        return $next($request);
    }

    /**
     * `{empresaId}` para rutas anidadas (Iva, Períodos, Cuentas...); `{id}` solo cuando la ruta
     * es la de la empresa en sí (`/empresas/{id}`) — se distingue por el prefijo de la URI para
     * no confundirlo con el `{id}` de otros recursos del mismo grupo (`/rubros/{id}`, etc.).
     */
    private function resolverEmpresaId(Request $request): ?int
    {
        $empresaId = $request->route('empresaId');
        if ($empresaId === null && str_starts_with($request->uri(), '/empresas/')) {
            $empresaId = $request->route('id');
        }

        return $empresaId !== null ? (int) $empresaId : null;
    }
}
