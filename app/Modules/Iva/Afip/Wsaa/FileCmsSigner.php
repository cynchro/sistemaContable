<?php

namespace App\Modules\Iva\Afip\Wsaa;

use RuntimeException;

/**
 * CmsSigner que carga el certificado y la clave desde archivos PEM recién al firmar
 * (no en construcción), para no hacer I/O al armar el grafo de objetos y que los
 * errores de configuración aparezcan en el punto de uso. Delega la firma en
 * OpenSslCmsSigner.
 */
final class FileCmsSigner implements CmsSigner
{
    public function __construct(
        private ?string $certPath,
        private ?string $keyPath,
        private string $keyPassphrase = '',
    ) {
    }

    public function sign(string $traXml): string
    {
        if (!$this->certPath || !$this->keyPath) {
            throw new RuntimeException(
                'AFIP_CERT_PATH / AFIP_KEY_PATH no configurados en .env (certificado WSAA).'
            );
        }

        $cert = @file_get_contents($this->certPath);
        $key  = @file_get_contents($this->keyPath);

        if ($cert === false || $key === false) {
            throw new RuntimeException('No se pudo leer el certificado o la clave de AFIP.');
        }

        return (new OpenSslCmsSigner($cert, $key, $this->keyPassphrase))->sign($traXml);
    }
}
