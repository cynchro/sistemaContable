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

    /**
     * Vista global del padrón: todos los sujetos del tenant, sin filtrar por empresa, con la
     * lista de empresas donde cada uno está activo (documento "Satélite Visual IVA" §10).
     * Paginado — `empresasActivasDe` solo resuelve las activaciones de los sujetos de ESTA
     * página (a lo sumo `$perPage` ids), no de todo el tenant.
     *
     * `filtros['rol']` separa el padrón único en dos vistas (proveedores / clientes, informe del
     * cliente 10/08/2026 pedido 5a) — ver `SujetoRepository::listAllByTenant`.
     *
     * @param  array{q?: ?string, rol?: ?string} $filtros
     * @return array{
     *     total: int, cantidad_por_pagina: int, pagina: int, cantidad_total: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function listGlobal(string $tenantId, array $filtros, int $page, int $perPage): array
    {
        $rol     = $filtros['rol'] ?? null;
        $pagina  = $this->sujetos->listAllByTenant($tenantId, $filtros, $page, $perPage);
        $ids     = array_map(static fn (array $s): int => (int) $s['id'], $pagina['results']);
        $porId   = $this->activaciones->empresasActivasDe($ids, $tenantId, $rol);

        $pagina['results'] = array_map(static function (array $s) use ($porId): array {
            $s['empresas'] = $porId[(int) $s['id']] ?? [];

            return $s;
        }, $pagina['results']);

        return $pagina;
    }

    /**
     * "CUIT único" (informe del cliente 10/08/2026, pedido 3): consulta global del padrón por
     * CUIT exacto, sin scope de empresa — usada por el alta de empresa para ofrecer reusar los
     * datos de un sujeto ya cargado en vez de tipearlos de nuevo.
     *
     * @return array<string, mixed>|null
     */
    public function findByCuitGlobal(string $tenantId, string $cuit): ?array
    {
        return $this->sujetos->findByCuit($tenantId, $cuit);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId, string $rol): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertActivo($id, $empresaId, $rol);

        return $this->sujetos->findById($id, $tenantId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId, string $rol): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertReferencias($data, $tenantId);
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
        $this->assertReferencias($data, $tenantId);

        if (array_key_exists('cuit', $data)) {
            $data['cuit'] = $this->assertCuitValido($data['cuit'] ?? '');
            $this->assertCuitLibre($data['cuit'], $id, $tenantId);
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
     * Condición de IVA y provincia son catálogos globales; `concepto_default_id` (documento
     * "Satélite Visual IVA" §5.2) es tenant-level, a diferencia de la vieja cuenta contable
     * directa (empresa-level) — se valida contra `iva_conceptos` con scope de tenant. 422 amable.
     *
     * @param array<string, mixed> $data
     */
    private function assertReferencias(array $data, string $tenantId): void
    {
        $this->refs->validate([
            'condicion_iva_id' => ['table' => 'condiciones_iva', 'value' => $data['condicion_iva_id'] ?? null],
            'provincia_id'     => ['table' => 'provincias', 'value' => $data['provincia_id'] ?? null],
            'concepto_default_id' => [
                'table' => 'iva_conceptos',
                'value' => $data['concepto_default_id'] ?? null,
                'scope' => ['tenant_id' => $tenantId],
            ],
        ]);
    }
}
