<?php

namespace App\Modules\Iva\Afip\Wsaa;

use RuntimeException;

/**
 * Firma CMS/PKCS#7 vía openssl. Recibe el certificado y la clave privada en PEM
 * (su contenido, no rutas) para no acoplarse al filesystem y poder testearse con
 * un certificado autofirmado generado al vuelo.
 */
final class OpenSslCmsSigner implements CmsSigner
{
    public function __construct(
        private string $certPem,
        private string $keyPem,
        private string $keyPassphrase = '',
    ) {
    }

    public function sign(string $traXml): string
    {
        $inFile  = tempnam(sys_get_temp_dir(), 'tra_');
        $outFile = tempnam(sys_get_temp_dir(), 'cms_');

        if ($inFile === false || $outFile === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal para firmar el TRA.');
        }

        try {
            file_put_contents($inFile, $traXml);

            $key = $this->keyPassphrase !== '' ? [$this->keyPem, $this->keyPassphrase] : $this->keyPem;

            $ok = openssl_pkcs7_sign(
                $inFile,
                $outFile,
                $this->certPem,
                $key,
                [],
                PKCS7_BINARY,
            );

            if ($ok === false) {
                throw new RuntimeException('openssl_pkcs7_sign falló: ' . openssl_error_string());
            }

            return $this->extractCmsBase64((string) file_get_contents($outFile));
        } finally {
            @unlink($inFile);
            @unlink($outFile);
        }
    }

    /**
     * openssl_pkcs7_sign produce un mensaje MIME (cabeceras + cuerpo base64).
     * El WSAA espera solo el CMS en base64 → se descarta todo hasta la línea en blanco
     * que separa cabeceras de cuerpo, y se quitan separadores de límite MIME.
     */
    private function extractCmsBase64(string $mime): string
    {
        $parts = preg_split("/\n\r?\n/", $mime, 2);

        if ($parts === false || count($parts) < 2) {
            throw new RuntimeException('Salida de openssl_pkcs7_sign con formato inesperado.');
        }

        $body  = $parts[1];
        $lines = preg_split('/\r?\n/', $body) ?: [];
        $b64   = '';

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '-----')) {
                continue;
            }
            $b64 .= trim($line);
        }

        return $b64;
    }
}
