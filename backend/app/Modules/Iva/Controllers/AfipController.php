<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Config;
use App\Support\Response;

/**
 * Información del ambiente AFIP activo, para que el frontend avise si está en
 * homologación (comprobantes de prueba, sin validez fiscal) o en producción.
 */
class AfipController
{
    public function ambiente(): Response
    {
        return Response::success([
            'env'  => (string) Config::get('afip.env', 'homologacion'),
            'cuit' => Config::get('afip.cuit'),
        ]);
    }
}
