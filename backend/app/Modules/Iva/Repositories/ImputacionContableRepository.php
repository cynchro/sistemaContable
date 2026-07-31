<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Reglas de imputación contable del Padrón Único (documento "Satélite Visual IVA" §5, ver
 * documentacion/analisis-satelite-visual-iva.md): resuelve, para un comprobante de compra de un
 * proveedor en una empresa (y opcionalmente un punto de venta), la CUENTA contable a imputar.
 *
 * Pasa por un nivel intermedio de "concepto" (tenant-level, `iva_conceptos`) porque `cuentas` es
 * un catálogo por-empresa: dos empresas no comparten fila de cuenta, así que una regla no puede
 * apuntar directo a una cuenta y valer para todo el estudio a la vez. El concepto sí es
 * compartible; cada empresa lo traduce a su propia cuenta vía `empresa_concepto_cuenta`.
 *
 * Cadena de resolución (de más a menos específica), ver migración 0051 para el detalle de tablas:
 *  1. Excepción de punto de venta para ESTA empresa (`iva_sujeto_punto_venta_empresa`).
 *  2. Regla de punto de venta GLOBAL del proveedor (`iva_sujeto_punto_venta`).
 *  3. Excepción del concepto por defecto para ESTA empresa (`iva_sujeto_empresas.concepto_id`).
 *  4. Concepto por defecto GLOBAL del proveedor (`iva_sujetos.concepto_default_id`).
 *  5. Traducción concepto→cuenta de ESTA empresa (`empresa_concepto_cuenta`); si no está
 *     mapeado, se resuelve null igual que "sin regla" (el comprobante queda sin imputar).
 */
class ImputacionContableRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Cuenta contable resuelta para el sujeto en la empresa, dado (opcionalmente) un punto de venta. */
    public function resolverCuenta(int $empresaId, int $sujetoId, ?string $puntoVenta): ?int
    {
        $conceptoId = $this->resolverConcepto($empresaId, $sujetoId, $puntoVenta);

        if ($conceptoId === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT cuenta_id FROM empresa_concepto_cuenta WHERE empresa_id = ? AND concepto_id = ?'
        );
        $stmt->execute([$empresaId, $conceptoId]);
        $cuentaId = $stmt->fetchColumn();

        return $cuentaId !== false && $cuentaId !== null ? (int) $cuentaId : null;
    }

    private function resolverConcepto(int $empresaId, int $sujetoId, ?string $puntoVenta): ?int
    {
        if ($puntoVenta !== null && $puntoVenta !== '') {
            $conceptoId = $this->conceptoDondeExiste(
                'iva_sujeto_punto_venta_empresa',
                'empresa_id = ? AND sujeto_id = ? AND punto_venta = ?',
                [$empresaId, $sujetoId, $puntoVenta],
            );
            if ($conceptoId !== null) {
                return $conceptoId;
            }

            $conceptoId = $this->conceptoDondeExiste(
                'iva_sujeto_punto_venta',
                'sujeto_id = ? AND punto_venta = ?',
                [$sujetoId, $puntoVenta],
            );
            if ($conceptoId !== null) {
                return $conceptoId;
            }
        }

        $conceptoId = $this->conceptoDondeExiste(
            'iva_sujeto_empresas',
            'empresa_id = ? AND sujeto_id = ?',
            [$empresaId, $sujetoId],
        );
        if ($conceptoId !== null) {
            return $conceptoId;
        }

        $stmt = $this->pdo->prepare('SELECT concepto_default_id FROM iva_sujetos WHERE id = ?');
        $stmt->execute([$sujetoId]);
        $conceptoId = $stmt->fetchColumn();

        return $conceptoId !== false && $conceptoId !== null ? (int) $conceptoId : null;
    }

    /** @param array<int, mixed> $params */
    private function conceptoDondeExiste(string $tabla, string $where, array $params): ?int
    {
        $stmt = $this->pdo->prepare("SELECT concepto_id FROM {$tabla} WHERE {$where}");
        $stmt->execute($params);
        $conceptoId = $stmt->fetchColumn();

        return $conceptoId !== false && $conceptoId !== null ? (int) $conceptoId : null;
    }

    /**
     * Reglas globales de punto de venta del proveedor, con la cuenta que resuelven en ESTA
     * empresa (null si el concepto todavía no está mapeado a una cuenta acá).
     *
     * @return list<array<string, mixed>>
     */
    public function reglasGlobales(int $empresaId, int $sujetoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pv.id, pv.punto_venta, pv.concepto_id, c.nombre AS concepto_nombre,
                    ecc.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre
               FROM iva_sujeto_punto_venta pv
               JOIN iva_conceptos c ON c.id = pv.concepto_id
               LEFT JOIN empresa_concepto_cuenta ecc
                      ON ecc.concepto_id = pv.concepto_id AND ecc.empresa_id = ?
               LEFT JOIN cuentas cu ON cu.id = ecc.cuenta_id
              WHERE pv.sujeto_id = ?
              ORDER BY pv.punto_venta'
        );
        $stmt->execute([$empresaId, $sujetoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert de la regla GLOBAL {punto_venta → concepto} del proveedor (todas las empresas). */
    public function setReglaGlobal(int $sujetoId, string $puntoVenta, int $conceptoId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_sujeto_punto_venta (sujeto_id, punto_venta, concepto_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE concepto_id = VALUES(concepto_id)'
        );
        $stmt->execute([$sujetoId, $puntoVenta, $conceptoId]);
    }

    public function deleteReglaGlobal(int $id, int $sujetoId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_sujeto_punto_venta WHERE id = ? AND sujeto_id = ?');
        $stmt->execute([$id, $sujetoId]);
    }

    /**
     * Excepciones de punto de venta específicas de ESTA empresa, con la cuenta que resuelven.
     *
     * @return list<array<string, mixed>>
     */
    public function reglasEmpresa(int $empresaId, int $sujetoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pv.id, pv.punto_venta, pv.concepto_id, c.nombre AS concepto_nombre,
                    ecc.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre
               FROM iva_sujeto_punto_venta_empresa pv
               JOIN iva_conceptos c ON c.id = pv.concepto_id
               LEFT JOIN empresa_concepto_cuenta ecc
                      ON ecc.concepto_id = pv.concepto_id AND ecc.empresa_id = pv.empresa_id
               LEFT JOIN cuentas cu ON cu.id = ecc.cuenta_id
              WHERE pv.empresa_id = ? AND pv.sujeto_id = ?
              ORDER BY pv.punto_venta'
        );
        $stmt->execute([$empresaId, $sujetoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert de la excepción {punto_venta → concepto} de esta empresa puntual. */
    public function setReglaEmpresa(int $empresaId, int $sujetoId, string $puntoVenta, int $conceptoId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_sujeto_punto_venta_empresa (empresa_id, sujeto_id, punto_venta, concepto_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE concepto_id = VALUES(concepto_id)'
        );
        $stmt->execute([$empresaId, $sujetoId, $puntoVenta, $conceptoId]);
    }

    public function deleteReglaEmpresa(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_sujeto_punto_venta_empresa WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /**
     * Mapeo concepto→cuenta propio de esta empresa (traduce el catálogo tenant-level de
     * conceptos al plan de cuentas real de la empresa).
     *
     * @return list<array<string, mixed>>
     */
    public function mapeoEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ecc.id, ecc.concepto_id, c.nombre AS concepto_nombre,
                    ecc.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre
               FROM empresa_concepto_cuenta ecc
               JOIN iva_conceptos c ON c.id = ecc.concepto_id
               JOIN cuentas cu ON cu.id = ecc.cuenta_id
              WHERE ecc.empresa_id = ?
              ORDER BY c.nombre'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setMapeoEmpresa(int $empresaId, int $conceptoId, int $cuentaId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO empresa_concepto_cuenta (empresa_id, concepto_id, cuenta_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE cuenta_id = VALUES(cuenta_id)'
        );
        $stmt->execute([$empresaId, $conceptoId, $cuentaId]);
    }

    public function deleteMapeoEmpresa(int $empresaId, int $conceptoId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM empresa_concepto_cuenta WHERE empresa_id = ? AND concepto_id = ?'
        );
        $stmt->execute([$empresaId, $conceptoId]);
    }
}
