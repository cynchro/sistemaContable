<?php

namespace App\Modules\Fiscal\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Fiscal\Repositories\VencimientoRepository;

/**
 * Vencimientos (obligaciones fiscales) por empresa (contribuyente), con workflow de
 * estados. Valida la pertenencia de la empresa al tenant antes de operar.
 */
class VencimientoService
{
    /** Estados del workflow (orden canónico del legacy). */
    public const ESTADOS = [
        'creado',
        'documentacion_recibida',
        'documentacion_cargada',
        'en_control',
        'presentado',
    ];

    public function __construct(
        private VencimientoRepository $vencimientos,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->vencimientos->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->vencimientos->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, ?int $usuarioId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        $data['estado'] = 'creado';
        $data['usuario_creador_id'] = $usuarioId;

        return $this->vencimientos->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, ?int $usuarioId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->vencimientos->findById($id, $empresaId);

        // El estado no se cambia por aquí (se usa cambiarEstado).
        unset($data['estado']);
        $data['usuario_actualizador_id'] = $usuarioId;
        $this->vencimientos->update($id, $data, $empresaId);

        return $this->vencimientos->findById($id, $empresaId);
    }

    /** @return array<string, mixed> */
    public function cambiarEstado(
        int $id,
        string $estado,
        ?string $observaciones,
        int $empresaId,
        ?int $usuarioId,
        string $tenantId,
    ): array {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->vencimientos->findById($id, $empresaId);

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ValidationException(['estado' => ['Estado de vencimiento inválido.']]);
        }

        $this->vencimientos->update($id, [
            'estado'                  => $estado,
            'observaciones_estado'    => $observaciones,
            'usuario_actualizador_id' => $usuarioId,
        ], $empresaId);

        return $this->vencimientos->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->vencimientos->findById($id, $empresaId);
        $this->vencimientos->delete($id, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }
}
