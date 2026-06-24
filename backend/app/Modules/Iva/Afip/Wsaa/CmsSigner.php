<?php

namespace App\Modules\Iva\Afip\Wsaa;

/**
 * Firma un TRA (XML) en formato CMS/PKCS#7 con el certificado del contribuyente.
 * Devuelve el CMS en base64, listo para enviarse a `loginCms`.
 */
interface CmsSigner
{
    public function sign(string $traXml): string;
}
