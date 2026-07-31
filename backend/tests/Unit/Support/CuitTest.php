<?php

namespace Tests\Unit\Support;

use App\Support\Cuit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Unit\UnitTestCase;

class CuitTest extends UnitTestCase
{
    public function test_normaliza_quitando_guiones_y_espacios(): void
    {
        $this->assertSame('20111111112', Cuit::normalizar('20-11111111-2'));
        $this->assertSame('20111111112', Cuit::normalizar('20 11111111 2'));
        $this->assertSame('20111111112', Cuit::normalizar('20111111112'));
    }

    #[DataProvider('cuitsValidos')]
    public function test_cuits_validos(string $cuit): void
    {
        $this->assertTrue(Cuit::esValido($cuit));
    }

    /** @return list<list<string>> */
    public static function cuitsValidos(): array
    {
        return [
            ['20111111112'],
            ['30111111118'],
            ['30710968973'],
            ['20-11111111-2'], // con guiones: se normaliza antes de validar
        ];
    }

    #[DataProvider('cuitsInvalidos')]
    public function test_cuits_invalidos(string $cuit): void
    {
        $this->assertFalse(Cuit::esValido($cuit));
    }

    /** @return list<list<string>> */
    public static function cuitsInvalidos(): array
    {
        return [
            ['20111111119'], // dígito verificador incorrecto (el válido es 2)
            ['30999999990'], // dígito verificador incorrecto
            ['123'],         // muy corto
            [''],            // vacío
            ['abcdefghijk'], // no numérico
        ];
    }
}
