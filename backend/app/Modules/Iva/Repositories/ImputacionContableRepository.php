<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Reglas de imputación contable del Padrón Único (documento "Satélite Visual IVA" §5, ver
 * documentacion/analisis-satelite-visual-iva.md §7.1): cuenta por defecto de un sujeto en una
 * empresa (`iva_sujeto_empresas.cuenta_id`) + excepción por punto de venta del proveedor
 * (`iva_sujeto_punto_venta`, caso MUCHAY SRL — un mismo proveedor factura conceptos distintos
 * según el punto de venta que usa).
 *
 * Resolución con precedencia: punto de venta específico → cuenta por defecto del sujeto en la
 * empresa → sin regla (null; el caller decide qué hacer, ej. bandeja de pendientes). Todavía no
 * está conectada a la creación/importación de compras — eso es un paso posterior (ver
 * documentacion/analisis-satelite-visual-iva.md §7.7).
 */
class ImputacionContableRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Cuenta contable resuelta para el sujeto en la empresa, dado (opcionalmente) un punto de
     * venta. Devuelve null si no hay ninguna regla cargada.
     */
    public function resolverCuenta(int $empresaId, int $sujetoId, ?string $puntoVenta): ?int
    {
        if ($puntoVenta !== null && $puntoVenta !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT cuenta_id FROM iva_sujeto_punto_venta
                  WHERE empresa_id = ? AND sujeto_id = ? AND punto_venta = ?'
            );
            $stmt->execute([$empresaId, $sujetoId, $puntoVenta]);
            $cuentaId = $stmt->fetchColumn();
            if ($cuentaId !== false) {
                return (int) $cuentaId;
            }
        }

        $stmt = $this->pdo->prepare(
            'SELECT cuenta_id FROM iva_sujeto_empresas WHERE empresa_id = ? AND sujeto_id = ?'
        );
        $stmt->execute([$empresaId, $sujetoId]);
        $cuentaId = $stmt->fetchColumn();

        return $cuentaId !== false && $cuentaId !== null ? (int) $cuentaId : null;
    }

    /** Reglas de punto de venta cargadas para el sujeto en la empresa. @return list<array<string, mixed>> */
    public function puntosVenta(int $empresaId, int $sujetoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pv.id, pv.punto_venta, pv.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre
               FROM iva_sujeto_punto_venta pv
               JOIN cuentas cu ON cu.id = pv.cuenta_id
              WHERE pv.empresa_id = ? AND pv.sujeto_id = ?
              ORDER BY pv.punto_venta'
        );
        $stmt->execute([$empresaId, $sujetoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert de la regla {punto_venta → cuenta} para el sujeto en la empresa. */
    public function setPuntoVenta(int $empresaId, int $sujetoId, string $puntoVenta, int $cuentaId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_sujeto_punto_venta (empresa_id, sujeto_id, punto_venta, cuenta_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cuenta_id = VALUES(cuenta_id)'
        );
        $stmt->execute([$empresaId, $sujetoId, $puntoVenta, $cuentaId]);
    }

    public function deletePuntoVenta(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_sujeto_punto_venta WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }
}
