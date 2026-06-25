<?php

namespace App\Modules\Iva\Audit;

use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;
use App\Modules\Iva\Repositories\AuditoriaRepository;

/**
 * Registra en `iva_audit_log` cada escritura exitosa (POST/PUT/PATCH/DELETE) sobre
 * las rutas de IVA: quién, cuándo, qué endpoint, sobre qué entidad y con qué datos.
 *
 * Se agrega al grupo de rutas de IVA (corre en todas, pero sólo audita escrituras
 * con respuesta < 400). La auditoría nunca rompe la operación: si el insert falla,
 * se ignora y se devuelve igual la respuesta del handler.
 */
class AuditMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private const METODOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private AuditoriaRepository $auditoria)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $metodo = strtoupper($request->method());
        $tenant = $request->tenantId();
        if ($tenant !== null && in_array($metodo, self::METODOS, true) && $response->getStatus() < 400) {
            try {
                $this->auditoria->registrar([
                    'tenant_id' => $tenant,
                    'user_id'   => $request->principal()?->userId,
                    'metodo'    => $metodo,
                    'uri'       => $request->uri(),
                    'params'    => $request->routeParams(),
                    'datos'     => $request->all(),
                    'status'    => $response->getStatus(),
                ]);
            } catch (\Throwable) {
                // La auditoría es best-effort: nunca debe afectar la operación de negocio.
            }
        }

        return $response;
    }
}
