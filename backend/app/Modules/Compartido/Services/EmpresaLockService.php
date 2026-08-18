<?php

namespace App\Modules\Compartido\Services;

use App\Exceptions\ConflictException;
use App\Modules\Compartido\Repositories\EmpresaLockRepository;
use App\Modules\Compartido\Repositories\EmpresaRepository;

/**
 * Reglas de "ocupación" de empresa (WhatsApp con el cliente, 11/08/2026): un usuario a la vez
 * trabaja sobre un contribuyente. Si otro usuario NO-admin intenta entrar, se lo bloquea del
 * todo. Un admin sí puede entrar, pero en modo observador (lo resuelve el llamador con el
 * resultado de `ocupar()` — la escritura la corta aparte `EmpresaLockMiddleware`).
 */
class EmpresaLockService
{
    public function __construct(
        private EmpresaLockRepository $locks,
        private EmpresaRepository $empresas,
    ) {
    }

    /**
     * @return array{modo: 'propio'|'observador', ocupado_por: ?string, desde: ?string}
     */
    public function ocupar(int $empresaId, string $tenantId, int $usuarioId, bool $esAdmin): array
    {
        $this->empresas->findById($empresaId, $tenantId); // valida pertenencia al tenant (404 si no)

        $estado = $this->locks->estadoDe($empresaId, $tenantId);
        if ($estado === null || (int) $estado['usuario_id'] === $usuarioId) {
            $this->locks->ocupar($empresaId, $usuarioId);

            return ['modo' => 'propio', 'ocupado_por' => null, 'desde' => null];
        }

        if ($esAdmin) {
            return ['modo' => 'observador', 'ocupado_por' => $estado['usuario_nombre'], 'desde' => $estado['desde']];
        }

        throw new ConflictException($this->mensajeOcupado($estado['usuario_nombre']));
    }

    public function ping(int $empresaId, int $usuarioId): void
    {
        $this->locks->ping($empresaId, $usuarioId);
    }

    public function liberar(int $empresaId, int $usuarioId): void
    {
        $this->locks->liberar($empresaId, $usuarioId);
    }

    public function mensajeOcupado(string $usuarioNombre): string
    {
        return "{$usuarioNombre} está trabajando en esta empresa ahora. Probá de nuevo en unos minutos.";
    }
}
