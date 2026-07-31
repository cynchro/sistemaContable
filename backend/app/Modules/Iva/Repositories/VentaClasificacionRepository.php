<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Motor de clasificación de ventas por punto de venta + tipo de comprobante (documento "Satélite
 * Visual IVA" §4, ver documentacion/analisis-satelite-visual-iva.md §7.1.4): regla general de un
 * punto de venta (`iva_venta_punto_venta`) + excepción por tipo de comprobante dentro de ese PV
 * (`iva_venta_punto_venta_tipo`, caso NC vs. Factura del documento).
 *
 * Resolución con precedencia: tipo específico → regla general del PV → sin regla (null; el
 * caller decide qué hacer). Todavía no hay endpoints HTTP ni UI para cargar estas reglas — se
 * ejercita instanciando el repositorio directo en los tests (mismo criterio que
 * ImputacionContableRepository en la Parte 1).
 */
class VentaClasificacionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Cuenta resuelta para el punto de venta en la empresa, dado (opcionalmente) un tipo de comprobante. */
    public function resolverCuenta(int $empresaId, string $puntoVenta, ?int $tipoComprobanteId): ?int
    {
        if ($tipoComprobanteId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT cuenta_id FROM iva_venta_punto_venta_tipo
                  WHERE empresa_id = ? AND punto_venta = ? AND tipo_comprobante_id = ?'
            );
            $stmt->execute([$empresaId, $puntoVenta, $tipoComprobanteId]);
            $cuentaId = $stmt->fetchColumn();
            if ($cuentaId !== false) {
                return (int) $cuentaId;
            }
        }

        $stmt = $this->pdo->prepare(
            'SELECT cuenta_id FROM iva_venta_punto_venta WHERE empresa_id = ? AND punto_venta = ?'
        );
        $stmt->execute([$empresaId, $puntoVenta]);
        $cuentaId = $stmt->fetchColumn();

        return $cuentaId !== false && $cuentaId !== null ? (int) $cuentaId : null;
    }

    /** Reglas generales por punto de venta cargadas para la empresa. @return list<array<string, mixed>> */
    public function reglasPuntoVenta(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pv.id, pv.punto_venta, pv.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre
               FROM iva_venta_punto_venta pv
               JOIN cuentas cu ON cu.id = pv.cuenta_id
              WHERE pv.empresa_id = ?
              ORDER BY pv.punto_venta'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert de la regla general {punto_venta → cuenta} de la empresa. */
    public function setPuntoVenta(int $empresaId, string $puntoVenta, int $cuentaId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_venta_punto_venta (empresa_id, punto_venta, cuenta_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE cuenta_id = VALUES(cuenta_id)'
        );
        $stmt->execute([$empresaId, $puntoVenta, $cuentaId]);
    }

    public function deletePuntoVenta(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_venta_punto_venta WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }

    /** Excepciones por tipo de comprobante dentro de un punto de venta. @return list<array<string, mixed>> */
    public function reglasPorTipo(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pvt.id, pvt.punto_venta, pvt.tipo_comprobante_id, tc.nombre AS tipo_comprobante_nombre,
                    pvt.cuenta_id, cu.codigo AS cuenta_codigo, cu.nombre AS cuenta_nombre
               FROM iva_venta_punto_venta_tipo pvt
               JOIN tipos_comprobante tc ON tc.id = pvt.tipo_comprobante_id
               JOIN cuentas cu           ON cu.id = pvt.cuenta_id
              WHERE pvt.empresa_id = ?
              ORDER BY pvt.punto_venta, tc.nombre'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert de la excepción {punto_venta, tipo_comprobante → cuenta} de la empresa. */
    public function setPorTipo(int $empresaId, string $puntoVenta, int $tipoComprobanteId, int $cuentaId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_venta_punto_venta_tipo (empresa_id, punto_venta, tipo_comprobante_id, cuenta_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cuenta_id = VALUES(cuenta_id)'
        );
        $stmt->execute([$empresaId, $puntoVenta, $tipoComprobanteId, $cuentaId]);
    }

    public function deletePorTipo(int $id, int $empresaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_venta_punto_venta_tipo WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
    }
}
