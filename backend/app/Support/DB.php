<?php

namespace App\Support;

class DB
{
    /** Contador para nombrar savepoints únicos en transacciones anidadas. */
    private int $savepointSeq = 0;

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Ejecuta $fn dentro de una transacción. Commitea al terminar bien, hace
     * rollback y re-lanza ante cualquier Throwable. Devuelve lo que retorne $fn.
     *
     * Es anidable: si ya hay una transacción activa (p. ej. otra
     * withTransaction más externa, o la transacción de aislamiento de los
     * Feature tests), usa un SAVEPOINT en lugar de abrir otra transacción
     * —PDO/MySQL no soportan transacciones anidadas reales—.
     */
    public function withTransaction(callable $fn): mixed
    {
        $nested    = $this->pdo->inTransaction();
        $savepoint = '';

        if ($nested) {
            $savepoint = 'sp_' . (++$this->savepointSeq);
            $this->pdo->exec("SAVEPOINT {$savepoint}");
        } else {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $fn($this->pdo);

            if ($nested) {
                $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            } else {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($nested) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            } else {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Ejecuta $fn tomando un lock consultivo con nombre (GET_LOCK de MySQL).
     * Serializa una sección crítica entre procesos/requests concurrentes sin
     * bloquear filas —por ejemplo la numeración de comprobantes por punto de
     * venta al emitir CAE: leer el último número autorizado, pedir el CAE a AFIP
     * y persistir deben correr uno a la vez para no repetir número—.
     *
     * $timeout es cuánto espera para *adquirir* el lock (no cuánto lo retiene);
     * si otra emisión está en curso, espera hasta $timeout segundos por ella.
     * Lanza si no lo consigue. Libera siempre al terminar (éxito o excepción).
     *
     * El nombre se acota a 64 chars (límite de MySQL). Los locks con nombre de
     * MySQL no participan de la transacción: se liberan con RELEASE_LOCK/fin de
     * sesión, no con commit/rollback —de ahí el finally—.
     */
    public function withLock(string $name, callable $fn, int $timeout = 10): mixed
    {
        $key = substr($name, 0, 64);

        $get = $this->pdo->prepare('SELECT GET_LOCK(?, ?)');
        $get->execute([$key, $timeout]);

        if ((int) $get->fetchColumn() !== 1) {
            throw new \RuntimeException("No se pudo obtener el lock '{$key}' (timeout {$timeout}s).");
        }

        try {
            return $fn($this->pdo);
        } finally {
            $rel = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $rel->execute([$key]);
        }
    }
}
