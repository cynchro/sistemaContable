<?php

namespace Tests\Unit\Support;

use App\Support\Crypto;
use Tests\Unit\UnitTestCase;

class CryptoTest extends UnitTestCase
{
    public function test_encrypt_then_decrypt_roundtrips(): void
    {
        $plano = 'clave-super-secreta-del-portal-123';

        $cifrado = Crypto::encrypt($plano);

        $this->assertNotSame($plano, $cifrado);
        $this->assertStringStartsWith('enc:v1:', $cifrado);
        $this->assertSame($plano, Crypto::decrypt($cifrado));
    }

    public function test_same_plaintext_yields_different_ciphertext(): void
    {
        // IV aleatorio por cifrado → dos cifrados del mismo texto no coinciden.
        $this->assertNotSame(Crypto::encrypt('igual'), Crypto::encrypt('igual'));
    }

    public function test_is_encrypted_detects_prefix(): void
    {
        $this->assertTrue(Crypto::isEncrypted(Crypto::encrypt('x')));
        $this->assertFalse(Crypto::isEncrypted('texto-plano'));
    }

    public function test_decrypt_passes_through_plaintext(): void
    {
        // Tolera valores guardados en claro antes de activar el cifrado.
        $this->assertSame('en-claro', Crypto::decrypt('en-claro'));
    }

    public function test_decrypt_fails_on_tampered_ciphertext(): void
    {
        $cifrado = Crypto::encrypt('integridad');
        $manipulado = $cifrado . 'XYZ';

        $this->expectException(\RuntimeException::class);
        Crypto::decrypt($manipulado);
    }
}
