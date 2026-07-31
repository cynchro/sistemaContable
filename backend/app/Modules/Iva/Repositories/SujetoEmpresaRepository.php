<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Activación de un sujeto del padrón único como cliente/proveedor de una empresa
 * puntual (`iva_sujeto_empresas`). No duplica identidad — solo arma el listado
 * "Clientes"/"Proveedores" de cada empresa y permite precargar un sujeto antes de
 * facturar con él.
 */
class SujetoEmpresaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Listado de sujetos activados por la empresa para el rol dado, con búsqueda por
     * nombre/CUIT y orden (nombre default | cuit).
     *
     * @param  array{q?: ?string, orden?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public function findAllByEmpresa(int $empresaId, string $rol, array $filtros = []): array
    {
        $where  = ['se.empresa_id = ?', 'se.rol = ?', "se.activo = 'S'"];
        $params = [$empresaId, $rol];

        if (!empty($filtros['q'])) {
            $where[]  = '(s.nombre LIKE ? OR s.cuit LIKE ?)';
            $q        = '%' . $filtros['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $orden = ($filtros['orden'] ?? '') === 'cuit' ? 's.cuit, s.nombre' : 's.nombre';

        $stmt = $this->pdo->prepare(
            'SELECT s.*, se.empresa_id AS empresa_id, se.activo AS activo, se.cuenta_id AS cuenta_id'
            . ' FROM iva_sujetos s JOIN iva_sujeto_empresas se ON se.sujeto_id = s.id'
            . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orden
        );
        $stmt->execute($params);

        return array_map([$this, 'decode'], (array) $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function existeActivo(int $empresaId, int $sujetoId, string $rol): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT EXISTS(SELECT 1 FROM iva_sujeto_empresas
                            WHERE empresa_id = ? AND sujeto_id = ? AND rol = ? AND activo = 'S')"
        );
        $stmt->execute([$empresaId, $sujetoId, $rol]);

        return (bool) $stmt->fetchColumn();
    }

    /** Activa (o reactiva) un sujeto como cliente/proveedor de la empresa. Idempotente. */
    public function activar(int $empresaId, int $sujetoId, string $rol): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO iva_sujeto_empresas (empresa_id, sujeto_id, rol) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE activo = 'S'"
        );
        $stmt->execute([$empresaId, $sujetoId, $rol]);
    }

    public function desactivar(int $empresaId, int $sujetoId, string $rol): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE iva_sujeto_empresas SET activo = 'N'
              WHERE empresa_id = ? AND sujeto_id = ? AND rol = ?"
        );
        $stmt->execute([$empresaId, $sujetoId, $rol]);

        return $stmt->rowCount() > 0;
    }

    /** Cuenta contable por defecto para este sujeto en esta empresa (null = sin regla). */
    public function setCuenta(int $empresaId, int $sujetoId, string $rol, ?int $cuentaId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE iva_sujeto_empresas SET cuenta_id = ?
              WHERE empresa_id = ? AND sujeto_id = ? AND rol = ?'
        );
        $stmt->execute([$cuentaId, $empresaId, $sujetoId, $rol]);
    }

    /** Cuenta contable por defecto actual (null = sin regla cargada). */
    public function cuentaDe(int $empresaId, int $sujetoId, string $rol): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT cuenta_id FROM iva_sujeto_empresas WHERE empresa_id = ? AND sujeto_id = ? AND rol = ?'
        );
        $stmt->execute([$empresaId, $sujetoId, $rol]);
        $cuentaId = $stmt->fetchColumn();

        return $cuentaId !== false && $cuentaId !== null ? (int) $cuentaId : null;
    }

    /**
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['cais'] = is_string($row['cais'] ?? null)
            ? (json_decode((string) $row['cais'], true) ?: [])
            : [];

        return $row;
    }
}
