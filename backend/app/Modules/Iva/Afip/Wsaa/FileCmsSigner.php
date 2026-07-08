<?php

namespace App\Modules\Iva\Afip\Wsaa;

use RuntimeException;

/**
 * CmsSigner que obtiene el certificado y la clave PEM recién al firmar (no en construcción),
 * para no hacer I/O al armar el grafo de objetos y que los errores de configuración aparezcan
 * en el punto de uso. Fuentes, en orden de prioridad: PEM inline (AFIP_CERT_PEM/AFIP_KEY_PEM,
 * recomendado en PaaS) o archivos (AFIP_CERT_PATH/AFIP_KEY_PATH). Delega en OpenSslCmsSigner.
 */
final class FileCmsSigner implements CmsSigner
{
    public function __construct(
        private ?string $certPath,
        private ?string $keyPath,
        private string $keyPassphrase = '',
        private ?string $certPem = null,
        private ?string $keyPem = null,
    ) {
    }

    public function sign(string $traXml): string
    {
        $cert = $this->resolve($this->certPem, $this->certPath, 'certificado');
        $key  = $this->resolve($this->keyPem, $this->keyPath, 'clave');

        return (new OpenSslCmsSigner($cert, $key, $this->keyPassphrase))->sign($traXml);
    }

    /** Devuelve el PEM inline si está; si no, lo lee del archivo. Lanza si no hay ninguno. */
    private function resolve(?string $pem, ?string $path, string $que): string
    {
        if ($pem !== null && $pem !== '') {
            return $pem;
        }
        if ($path === null || $path === '') {
            throw new RuntimeException(
                "AFIP: falta el {$que} WSAA (definí AFIP_*_PEM inline o AFIP_*_PATH en el entorno)."
            );
        }
        $contenido = @file_get_contents($path);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer el {$que} de AFIP desde {$path}.");
        }

        return $contenido;
    }
}
