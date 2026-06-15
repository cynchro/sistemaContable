<?php

namespace App\Support;

class DB
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Wraps $fn in a PDO transaction. Commits on success, rolls back and
     * re-throws on any Throwable. Returns whatever $fn returns.
     */
    public function withTransaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
