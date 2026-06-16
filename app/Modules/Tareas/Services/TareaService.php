<?php

namespace App\Modules\Tareas\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Tareas\Repositories\TareaRepository;

/**
 * Workflow de tareas del estudio (tenant). Cada tarea puede referir a una empresa
 * (contribuyente) y a usuarios (creador/asignado). Estado con historial y comentarios.
 */
class TareaService
{
    public const ESTADOS = ['pendiente', 'en_progreso', 'en_revision', 'completada', 'cancelada'];
    public const PRIORIDADES = ['baja', 'media', 'alta', 'urgente'];

    public function __construct(
        private TareaRepository $tareas,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $tenantId): array
    {
        return $this->tareas->findAll($tenantId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, string $tenantId): array
    {
        return $this->tareas->findById($id, $tenantId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?int $usuarioId, string $tenantId): array
    {
        $this->assertPrioridad($data['prioridad'] ?? null);
        $this->assertEmpresaOpcional($data['empresa_id'] ?? null, $tenantId);

        $data['estado'] = 'pendiente';
        $data['created_by'] = $usuarioId;

        $tarea = $this->tareas->create($data, $tenantId);
        $this->tareas->addHistorial((int) $tarea['id'], null, 'pendiente', $usuarioId, 'Creada');

        return $tarea;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, string $tenantId): array
    {
        $this->tareas->findById($id, $tenantId);
        $this->assertPrioridad($data['prioridad'] ?? null);
        $this->assertEmpresaOpcional($data['empresa_id'] ?? null, $tenantId);

        unset($data['estado'], $data['created_by']); // el estado se cambia por cambiarEstado
        $this->tareas->update($id, $data, $tenantId);

        return $this->tareas->findById($id, $tenantId);
    }

    /** @return array<string, mixed> */
    public function cambiarEstado(int $id, string $estado, ?string $obs, ?int $usuarioId, string $tenantId): array
    {
        $tarea = $this->tareas->findById($id, $tenantId);

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ValidationException(['estado' => ['Estado de tarea inválido.']]);
        }

        $anterior = (string) $tarea['estado'];
        $this->tareas->update($id, ['estado' => $estado], $tenantId);
        $this->tareas->addHistorial($id, $anterior, $estado, $usuarioId, $obs);

        return $this->tareas->findById($id, $tenantId);
    }

    public function delete(int $id, string $tenantId): void
    {
        $this->tareas->findById($id, $tenantId);
        $this->tareas->delete($id, $tenantId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comentarios(int $id, string $tenantId): array
    {
        $this->tareas->findById($id, $tenantId);

        return $this->tareas->comentarios($id);
    }

    /** @return list<array<string, mixed>> */
    public function agregarComentario(int $id, string $comentario, ?int $usuarioId, string $tenantId): array
    {
        $this->tareas->findById($id, $tenantId);
        $this->tareas->addComentario($id, $usuarioId, $comentario);

        return $this->tareas->comentarios($id);
    }

    /** @return list<array<string, mixed>> */
    public function historial(int $id, string $tenantId): array
    {
        $this->tareas->findById($id, $tenantId);

        return $this->tareas->historial($id);
    }

    private function assertPrioridad(mixed $prioridad): void
    {
        if ($prioridad !== null && !in_array($prioridad, self::PRIORIDADES, true)) {
            throw new ValidationException(['prioridad' => ['Prioridad inválida.']]);
        }
    }

    private function assertEmpresaOpcional(mixed $empresaId, string $tenantId): void
    {
        if ($empresaId !== null && $empresaId !== '') {
            $this->empresas->findById((int) $empresaId, $tenantId);
        }
    }
}
