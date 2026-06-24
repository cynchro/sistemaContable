<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\PermissionChecker;
use Tests\Unit\UnitTestCase;

class PermissionCheckerTest extends UnitTestCase
{
    /** @param array<string,mixed>|false $row */
    private function checkerReturning(array|false $row): PermissionChecker
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return new PermissionChecker($pdo);
    }

    public function test_level_returns_estado_from_row(): void
    {
        $checker = $this->checkerReturning(['estado' => 2]);
        $this->assertSame(PermissionChecker::LEVEL_WRITE, $checker->level(1, 'facturas'));
    }

    public function test_level_returns_none_when_max_is_null(): void
    {
        // MAX(estado) sobre cero filas devuelve una fila con estado = NULL.
        $checker = $this->checkerReturning(['estado' => null]);
        $this->assertSame(PermissionChecker::LEVEL_NONE, $checker->level(1, 'inexistente'));
    }

    public function test_allows_true_when_level_meets_minimum(): void
    {
        $checker = $this->checkerReturning(['estado' => 1]);
        $this->assertTrue($checker->allows(1, 'facturas')); // min por defecto = READ
        $this->assertFalse($checker->allows(1, 'facturas', PermissionChecker::LEVEL_WRITE));
    }

    public function test_allows_false_when_no_permission(): void
    {
        $checker = $this->checkerReturning(['estado' => null]);
        $this->assertFalse($checker->allows(1, 'facturas'));
    }
}
