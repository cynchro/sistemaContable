<?php

namespace Tests\Unit\Modules\Auth;

use App\Modules\Auth\Requests\AuthRequest;
use App\Exceptions\ValidationException;
use Tests\Unit\UnitTestCase;

class AuthRequestTest extends UnitTestCase
{
    public function test_valid_data_creates_request(): void
    {
        $request = new AuthRequest([
            'usuario' => 'user@example.com',
            'clave'   => 'secret123',
        ]);

        $this->assertSame('user@example.com', $request->input('usuario'));
        $this->assertSame('secret123', $request->input('clave'));
    }

    public function test_missing_usuario_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        new AuthRequest(['clave' => 'secret123']);
    }

    public function test_invalid_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        new AuthRequest(['usuario' => 'not-an-email', 'clave' => 'secret123']);
    }

    public function test_missing_clave_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        new AuthRequest(['usuario' => 'user@example.com']);
    }

    public function test_clave_too_short_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        new AuthRequest(['usuario' => 'user@example.com', 'clave' => 'abc']);
    }

    public function test_validation_exception_has_field_errors(): void
    {
        try {
            new AuthRequest([]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('usuario', $errors);
            $this->assertArrayHasKey('clave', $errors);
        }
    }

    public function test_default_rol_is_zero(): void
    {
        $request = new AuthRequest(['usuario' => 'x@x.com', 'clave' => 'password']);
        $this->assertSame(0, (int) $request->input('rol', 0));
    }

    public function test_rol_is_returned(): void
    {
        $request = new AuthRequest(['usuario' => 'x@x.com', 'clave' => 'password', 'rol' => 1]);
        $this->assertSame(1, (int) $request->input('rol'));
    }
}
