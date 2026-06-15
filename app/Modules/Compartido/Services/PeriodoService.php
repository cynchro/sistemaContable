<?php

namespace App\Modules\Compartido\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;

/**
 * Reglas de negocio de períodos (ingeniería inversa del sistema IVA):
 *  - el período pertenece a una empresa, que debe ser del tenant que opera;
 *  - fecha_fin no puede ser anterior a fecha_ini;
 *  - un período cerrado no se modifica ni se borra hasta reabrirlo;
 *  - cerrar/abrir alterna el estado de forma idempotente-segura (no recerrar).
 *
 * Nota: la regla "no borrar un período con comprobantes" se completará cuando
 * exista el módulo Iva (ventas/compras) — ver docs/ingenieria-inversa/iva.md.
 */
class PeriodoService
{
    public function __construct(
        private PeriodoRepository $periodos,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->periodos->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->periodos->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertRangoFechas($data['fecha_ini'] ?? null, $data['fecha_fin'] ?? null);

        return $this->periodos->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $periodo = $this->periodos->findById($id, $empresaId);
        $this->assertAbierto($periodo, 'modificar');

        // Valida el rango resultante (los campos no enviados conservan su valor).
        $this->assertRangoFechas(
            $data['fecha_ini'] ?? $periodo['fecha_ini'],
            $data['fecha_fin'] ?? $periodo['fecha_fin'],
        );

        $this->periodos->update($id, $data, $empresaId);

        return $this->periodos->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $periodo = $this->periodos->findById($id, $empresaId);
        $this->assertAbierto($periodo, 'eliminar');

        $this->periodos->delete($id, $empresaId);
    }

    /** @return array<string, mixed> */
    public function cerrar(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $periodo = $this->periodos->findById($id, $empresaId);

        if ($periodo['cerrado'] === 'S') {
            throw new ConflictException('El período ya está cerrado.');
        }

        $this->periodos->setCerrado($id, $empresaId, true);

        return $this->periodos->findById($id, $empresaId);
    }

    /** @return array<string, mixed> */
    public function abrir(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $periodo = $this->periodos->findById($id, $empresaId);

        if ($periodo['cerrado'] !== 'S') {
            throw new ConflictException('El período ya está abierto.');
        }

        $this->periodos->setCerrado($id, $empresaId, false);

        return $this->periodos->findById($id, $empresaId);
    }

    /** Valida que la empresa exista y pertenezca al tenant (lanza NotFound si no). */
    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }

    /** @param array<string, mixed> $periodo */
    private function assertAbierto(array $periodo, string $accion): void
    {
        if (($periodo['cerrado'] ?? 'N') === 'S') {
            throw new ConflictException("No se puede {$accion} un período cerrado; reabrilo primero.");
        }
    }

    private function assertRangoFechas(?string $ini, ?string $fin): void
    {
        if ($ini !== null && $fin !== null && $fin < $ini) {
            throw new ValidationException([
                'fecha_fin' => ['fecha_fin no puede ser anterior a fecha_ini.'],
            ]);
        }
    }
}
