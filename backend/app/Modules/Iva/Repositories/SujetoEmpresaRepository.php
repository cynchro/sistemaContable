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
            'SELECT s.*, se.empresa_id AS empresa_id, se.activo AS activo'
            . ' FROM iva_sujetos s JOIN iva_sujeto_empresas se ON se.sujeto_id = s.id'
            . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orden
        );
        $stmt->execute($params);

        return array_map([$this, 'decode'], (array) $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Empresas donde cada sujeto está activo (y con qué rol), para la vista global del padrón
     * (documento "Satélite Visual IVA" §10, Etapa 4).
     *
     * @param  list<int> $sujetoIds
     * @return array<int, list<array{empresa_id:int, empresa_nombre:string, rol:string}>> indexado por sujeto_id
     */
    public function empresasActivasDe(array $sujetoIds, string $tenantId): array
    {
        if ($sujetoIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sujetoIds), '?'));
        $stmt         = $this->pdo->prepare(
            "SELECT se.sujeto_id, se.empresa_id, e.nombre AS empresa_nombre, se.rol
               FROM iva_sujeto_empresas se
               JOIN empresas e ON e.id = se.empresa_id
              WHERE se.sujeto_id IN ({$placeholders}) AND se.activo = 'S' AND e.tenant_id = ?
              ORDER BY e.nombre"
        );
        $stmt->execute([...$sujetoIds, $tenantId]);

        $porSujeto = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $porSujeto[(int) $row['sujeto_id']][] = [
                'empresa_id'     => (int) $row['empresa_id'],
                'empresa_nombre' => $row['empresa_nombre'],
                'rol'            => $row['rol'],
            ];
        }

        return $porSujeto;
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

    /**
     * Excepción del concepto por defecto del proveedor para ESTA empresa puntual (documento
     * "Satélite Visual IVA" §5.2, null = usar el default global de `iva_sujetos`).
     */
    public function setConcepto(int $empresaId, int $sujetoId, string $rol, ?int $conceptoId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE iva_sujeto_empresas SET concepto_id = ?
              WHERE empresa_id = ? AND sujeto_id = ? AND rol = ?'
        );
        $stmt->execute([$conceptoId, $empresaId, $sujetoId, $rol]);
    }

    /** Excepción de concepto vigente para esta empresa (null = no hay, usa el default global). */
    public function conceptoDe(int $empresaId, int $sujetoId, string $rol): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT concepto_id FROM iva_sujeto_empresas WHERE empresa_id = ? AND sujeto_id = ? AND rol = ?'
        );
        $stmt->execute([$empresaId, $sujetoId, $rol]);
        $conceptoId = $stmt->fetchColumn();

        return $conceptoId !== false && $conceptoId !== null ? (int) $conceptoId : null;
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
