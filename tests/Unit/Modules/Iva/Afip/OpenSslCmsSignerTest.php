<?php

namespace Tests\Unit\Modules\Iva\Afip;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsaa\OpenSslCmsSigner;

class OpenSslCmsSignerTest extends UnitTestCase
{
    /** @return array{0:string,1:string} cert PEM, key PEM */
    private function selfSigned(): array
    {
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($pkey === false) {
            $this->markTestSkipped('openssl_pkey_new no disponible en este entorno.');
        }

        $csr  = openssl_csr_new(['commonName' => 'test-wsaa'], $pkey);
        $x509 = openssl_csr_sign($csr, null, $pkey, 1);

        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($pkey, $keyPem);

        return [$certPem, $keyPem];
    }

    public function test_firma_produce_cms_der_en_base64(): void
    {
        [$cert, $key] = $this->selfSigned();

        $cms = (new OpenSslCmsSigner($cert, $key))->sign('<loginTicketRequest version="1.0"/>');

        $this->assertNotEmpty($cms);
        $der = base64_decode($cms, true);
        $this->assertNotFalse($der, 'el CMS debe ser base64 válido');
        // Un CMS DER arranca con un SEQUENCE ASN.1 (0x30).
        $this->assertSame(0x30, ord($der[0]));
    }
}
