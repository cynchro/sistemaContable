<?php

namespace App\Modules\Contribuyentes\Services;

use App\Support\Crypto;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Contribuyentes\Repositories\CredencialRepository;

/**
 * Credenciales de acceso de un contribuyente (empresa): portales fiscales y
 * procesadoras de tarjeta. Cifra la `clave` antes de persistirla y la devuelve en
 * claro al leer (el estudio la necesita para operar). Valida la pertenencia de la
 * empresa al tenant antes de operar.
 */
class CredencialService
{
    public function __construct(
        private CredencialRepository $credenciales,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return array_map([$this, 'reveal'], $this->credenciales->findAllByEmpresa($empresaId));
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->reveal($this->credenciales->findById($id, $empresaId));
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->reveal($this->credenciales->create($this->conceal($data), $empresaId));
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->credenciales->findById($id, $empresaId);
        $this->credenciales->update($id, $this->conceal($data), $empresaId);

        return $this->reveal($this->credenciales->findById($id, $empresaId));
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->credenciales->findById($id, $empresaId);
        $this->credenciales->delete($id, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }

    /**
     * Cifra la `clave` si viene en el payload. Una clave vacía se guarda como NULL.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function conceal(array $data): array
    {
        if (!array_key_exists('clave', $data)) {
            return $data;
        }

        $clave = $data['clave'];
        $data['clave'] = ($clave === null || $clave === '')
            ? null
            : Crypto::encrypt((string) $clave);

        return $data;
    }

    /**
     * Descifra la `clave` para devolverla en claro.
     *
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function reveal(array $row): array
    {
        if (isset($row['clave']) && $row['clave'] !== '') {
            $row['clave'] = Crypto::decrypt((string) $row['clave']);
        }

        return $row;
    }
}
