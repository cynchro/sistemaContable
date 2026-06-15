<?php

namespace Tests\Unit\Support;

use App\Support\DB;
use Tests\Unit\UnitTestCase;

class DBTest extends UnitTestCase
{
    public function test_with_transaction_commits_and_returns_value(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $db     = new DB($pdo);
        $result = $db->withTransaction(fn() => 'done');

        $this->assertSame('done', $result);
    }

    public function test_with_transaction_rolls_back_on_exception(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack');

        $db = new DB($pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $db->withTransaction(function () {
            throw new \RuntimeException('boom');
        });
    }

    public function test_with_transaction_rethrows_after_rollback(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('beginTransaction');
        $pdo->method('rollBack');

        $db        = new DB($pdo);
        $exception = new \InvalidArgumentException('bad input');

        try {
            $db->withTransaction(function () use ($exception) {
                throw $exception;
            });
            $this->fail('Expected exception not thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($exception, $e);
        }
    }
}
