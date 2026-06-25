<?php

namespace App\Modules\Iva\Repositories;

use App\Helpers\PaginatorHelper;
use PDO;

/**
 * Persistencia del registro de auditoría de IVA (`iva_audit_log`). Insert desde el
 * AuditMiddleware y listado paginado por tenant (más reciente primero).
 */
class AuditoriaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Registra una operación de escritura. No debe propagar errores (la auditoría no
     * puede romper la operación de negocio): el AuditMiddleware ya envuelve la llamada.
     *
     * @param array{
     *   tenant_id: string, user_id: ?int, metodo: string, uri: string,
     *   params: array<string, mixed>, datos: array<string, mixed>, status: int
     * } $entrada
     */
    public function registrar(array $entrada): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_audit_log (tenant_id, user_id, metodo, uri, params, datos, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $entrada['tenant_id'],
            $entrada['user_id'],
            $entrada['metodo'],
            $entrada['uri'],
            json_encode($entrada['params'], JSON_UNESCAPED_UNICODE),
            json_encode($entrada['datos'], JSON_UNESCAPED_UNICODE),
            $entrada['status'],
        ]);
    }

    /**
     * Listado paginado de auditoría del tenant, más reciente primero.
     *
     * @return array<string, mixed>
     */
    public function listar(string $tenantId, int $page, int $perPage): array
    {
        $query = 'SELECT id, user_id, metodo, uri, params, datos, status, created_at
                    FROM iva_audit_log WHERE tenant_id = ? ORDER BY id DESC';

        return (new PaginatorHelper($this->pdo, $query, $page, $perPage, true, [$tenantId]))->getPaginatedResults();
    }
}
