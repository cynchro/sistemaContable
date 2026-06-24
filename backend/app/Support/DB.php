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
}
