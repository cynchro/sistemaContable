<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Actividades (NAES) por empresa y el mapa {punto_venta → actividad} de la estrategia
 * "por punto de venta" para la apertura de la DJ IVA Simple. Acotado a `empresa_id`
 * (la pertenencia de la empresa al tenant la valida el Service).
 */
class EmpresaActividadRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empresa_actividades WHERE empresa_id = ? ORDER BY codigo');
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function create(int $empresaId, string $codigo, ?string $descripcion): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO empresa_actividades (empresa_id, codigo, descripcion) VALUES (?, ?, ?)'
        );
        $stmt->execute([$empresaId, $codigo, $descripcion]);

        return $this->find((int) $this->pdo->lastInsertId(), $empresaId);
    }

    /** @return array<string, mixed> */
    public function find(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empresa_actividades WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Actividad', $id);
        }

        return $row;
    }

    public function delete(int $id, int $empresaId): void
    {
        $this->find($id, $empresaId);
        $stmt = $this->pdo->prepare('DELETE FROM empresa_actividades WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /** Mapa de puntos de venta con el código/descr. de la actividad. @return list<array<string,mixed>> */
    public function puntosVenta(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT apv.id, apv.punto_venta, apv.actividad_id, ea.codigo AS actividad_codigo,
                    ea.descripcion AS actividad_descripcion
               FROM actividad_punto_venta apv
               JOIN empresa_actividades ea ON ea.id = apv.actividad_id
              WHERE apv.empresa_id = ? ORDER BY apv.punto_venta'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert del mapeo {punto_venta → actividad} (único por empresa+punto_venta). */
    public function setPuntoVenta(int $empresaId, string $puntoVenta, int $actividadId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO actividad_punto_venta (empresa_id, punto_venta, actividad_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE actividad_id = VALUES(actividad_id)'
        );
        $stmt->execute([$empresaId, $puntoVenta, $actividadId]);
    }

    public function deletePuntoVenta(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM actividad_punto_venta WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /** Mapa {alícuota → actividad} (estrategia construcción). @return list<array<string,mixed>> */
    public function alicuotas(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT aa.id, aa.alicuota, aa.actividad_id, ea.codigo AS actividad_codigo,
                    ea.descripcion AS actividad_descripcion
               FROM actividad_alicuota aa
               JOIN empresa_actividades ea ON ea.id = aa.actividad_id
              WHERE aa.empresa_id = ? ORDER BY aa.alicuota'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setAlicuota(int $empresaId, string $alicuota, int $actividadId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO actividad_alicuota (empresa_id, alicuota, actividad_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE actividad_id = VALUES(actividad_id)'
        );
        $stmt->execute([$empresaId, $alicuota, $actividadId]);
    }

    public function deleteAlicuota(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM actividad_alicuota WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /** Mapa {cliente → actividad} (estrategia por receptor). @return list<array<string,mixed>> */
    public function receptores(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ar.id, ar.cliente_id, c.nombre AS cliente_nombre, c.cuit AS cliente_cuit,
                    ar.actividad_id, ea.codigo AS actividad_codigo, ea.descripcion AS actividad_descripcion
               FROM actividad_receptor ar
               JOIN empresa_actividades ea ON ea.id = ar.actividad_id
               LEFT JOIN iva_clientes c ON c.id = ar.cliente_id
              WHERE ar.empresa_id = ? ORDER BY c.nombre'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setReceptor(int $empresaId, int $clienteId, int $actividadId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO actividad_receptor (empresa_id, cliente_id, actividad_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE actividad_id = VALUES(actividad_id)'
        );
        $stmt->execute([$empresaId, $clienteId, $actividadId]);
    }

    public function deleteReceptor(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM actividad_receptor WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /** Coeficientes {actividad → participación 0..1} (estrategia porcentajes fijos). @return list<array<string,mixed>> */
    public function coeficientes(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ac.id, ac.actividad_id, ac.coeficiente, ea.codigo AS actividad_codigo,
                    ea.descripcion AS actividad_descripcion
               FROM actividad_coeficiente ac
               JOIN empresa_actividades ea ON ea.id = ac.actividad_id
              WHERE ac.empresa_id = ? ORDER BY ea.codigo'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setCoeficiente(int $empresaId, int $actividadId, string $coeficiente): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO actividad_coeficiente (empresa_id, actividad_id, coeficiente) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE coeficiente = VALUES(coeficiente)'
        );
        $stmt->execute([$empresaId, $actividadId, $coeficiente]);
    }

    public function deleteCoeficiente(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM actividad_coeficiente WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /** Código de la primera actividad de la empresa (default para comprobantes sin resolver). */
    public function codigoDefault(int $empresaId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT codigo FROM empresa_actividades WHERE empresa_id = ? ORDER BY id LIMIT 1');
        $stmt->execute([$empresaId]);
        $codigo = $stmt->fetchColumn();

        return $codigo !== false ? (string) $codigo : null;
    }
}
