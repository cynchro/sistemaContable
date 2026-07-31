<?php

namespace App\Modules\Iva\Services;

use App\Support\Cuit;
use App\Support\DB;
use App\Support\ReferenceValidator;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\SujetoRepository;
use App\Modules\Iva\Repositories\SujetoEmpresaRepository;

/**
 * Padrón Único de Sujetos IVA. Un mismo Service atiende clientes y proveedores (el
 * `rol` decide la activación por empresa) porque la identidad es la misma tabla: dar
 * de alta un CUIT que ya existe en el padrón del tenant reutiliza esa fila (upsert por
 * CUIT) en lugar de duplicarla — es la regla que pidió el contador (ver
 * documentacion/pedido-padron-unico-contribuyentes.md).
 */
class SujetoService
{
    public function __construct(
        private SujetoRepository $sujetos,
        private SujetoEmpresaRepository $activaciones,
        private EmpresaRepository $empresas,
        private ReferenceValidator $refs,
        private DB $db,
    ) {
    }

    /**
     * @param  array{q?: ?string, orden?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public function list(int $empresaId, string $tenantId, string $rol, array $filtros = []): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->activaciones->findAllByEmpresa($empresaId, $rol, $filtros);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId, string $rol): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertActivo($id, $empresaId, $rol);

        $sujeto = $this->sujetos->findById($id, $tenantId);
        $sujeto['cuenta_id'] = $this->activaciones->cuentaDe($empresaId, $id, $rol);

        return $sujeto;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId, string $rol): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertReferencias($data);
        $data['cuit'] = $this->assertCuitValido($data['cuit'] ?? '');

        return $this->db->withTransaction(function () use ($data, $empresaId, $tenantId, $rol) {
            $existente = $this->sujetos->findByCuit($tenantId, $data['cuit']);

            if ($existente !== null) {
                $this->sujetos->update((int) $existente['id'], $data, $tenantId);
                $sujeto = $this->sujetos->findById((int) $existente['id'], $tenantId);
            } else {
                $sujeto = $this->sujetos->create($data, $tenantId);
            }

            $this->activaciones->activar($empresaId, (int) $sujeto['id'], $rol);

            return $sujeto;
        });
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId, string $rol): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertActivo($id, $empresaId, $rol);
        $this->assertReferencias($data);

        if (array_key_exists('cuit', $data)) {
            $data['cuit'] = $this->assertCuitValido($data['cuit'] ?? '');
            $this->assertCuitLibre($data['cuit'], $id, $tenantId);
        }

        if (array_key_exists('cuenta_id', $data)) {
            $cuentaId = $data['cuenta_id'] !== null ? (int) $data['cuenta_id'] : null;
            $this->assertCuentaValida($cuentaId, $empresaId);
            $this->activaciones->setCuenta($empresaId, $id, $rol, $cuentaId);
        }

        $this->sujetos->update($id, $data, $tenantId);

        return $this->get($id, $empresaId, $tenantId, $rol);
    }

    /** Desactiva el sujeto para esta empresa (no borra el padrón: puede seguir en uso en otras). */
    public function delete(int $id, int $empresaId, string $tenantId, string $rol): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertActivo($id, $empresaId, $rol);
        $this->activaciones->desactivar($empresaId, $id, $rol);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }

    private function assertActivo(int $sujetoId, int $empresaId, string $rol): void
    {
        if (!$this->activaciones->existeActivo($empresaId, $sujetoId, $rol)) {
            throw new NotFoundException($rol === 'proveedor' ? 'Proveedor' : 'Cliente', $sujetoId);
        }
    }

    /** Normaliza y valida el CUIT (dígito verificador AFIP); 422 si no es válido. */
    private function assertCuitValido(string $cuit): string
    {
        $normalizado = Cuit::normalizar($cuit);

        if (!Cuit::esValido($normalizado)) {
            throw new ValidationException(['cuit' => ['El CUIT no es válido (dígito verificador incorrecto).']]);
        }

        return $normalizado;
    }

    /** El CUIT no puede pertenecer a otro sujeto del padrón del tenant. */
    private function assertCuitLibre(string $cuit, int $exceptId, string $tenantId): void
    {
        $otro = $this->sujetos->findByCuit($tenantId, $cuit);

        if ($otro !== null && (int) $otro['id'] !== $exceptId) {
            throw new ValidationException(['cuit' => ['Ese CUIT ya pertenece a otro sujeto del padrón.']]);
        }
    }

    /**
     * La cuenta contable por defecto (documento "Satélite Visual IVA" §5) pertenece al plan de
     * cuentas de esta empresa puntual — a diferencia de condición IVA/provincia, no es un
     * catálogo global.
     */
    private function assertCuentaValida(?int $cuentaId, int $empresaId): void
    {
        $this->refs->validate([
            'cuenta_id' => [
                'table' => 'cuentas', 'value' => $cuentaId, 'scope' => ['empresa_id' => $empresaId],
            ],
        ]);
    }

    /**
     * Condición de IVA y provincia son catálogos globales; validación amable → 422.
     *
     * @param array<string, mixed> $data
     */
    private function assertReferencias(array $data): void
    {
        $this->refs->validate([
            'condicion_iva_id' => ['table' => 'condiciones_iva', 'value' => $data['condicion_iva_id'] ?? null],
            'provincia_id'     => ['table' => 'provincias', 'value' => $data['provincia_id'] ?? null],
        ]);
    }
}
