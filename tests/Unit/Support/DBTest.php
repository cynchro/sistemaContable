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

    public function test_anidada_usa_savepoint_y_lo_libera(): void
    {
        $execs = [];
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->method('exec')->willReturnCallback(function (string $sql) use (&$execs): int {
            $execs[] = $sql;
            return 0;
        });

        $result = (new DB($pdo))->withTransaction(fn () => 'ok');

        $this->assertSame('ok', $result);
        $this->assertStringContainsString('SAVEPOINT', $execs[0]);
        $this->assertStringContainsString('RELEASE SAVEPOINT', $execs[1]);
    }

    public function test_anidada_hace_rollback_al_savepoint_ante_excepcion(): void
    {
        $execs = [];
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('rollBack');
        $pdo->method('exec')->willReturnCallback(function (string $sql) use (&$execs): int {
            $execs[] = $sql;
            return 0;
        });

        try {
            (new DB($pdo))->withTransaction(fn () => throw new \RuntimeException('boom'));
            $this->fail('Se esperaba excepción.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertStringContainsString('SAVEPOINT', $execs[0]);
        $this->assertStringContainsString('ROLLBACK TO SAVEPOINT', $execs[1]);
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
