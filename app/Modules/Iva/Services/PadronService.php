<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Iva\Afip\Padron\PadronClient;
use App\Modules\Iva\Afip\Padron\PersonaPadron;

/**
 * Consulta de padrón AFIP por CUIT, para autocompletar datos de clientes/proveedores.
 * Valida el formato del CUIT antes de pegarle a AFIP.
 */
class PadronService
{
    public function __construct(private PadronClient $padron)
    {
    }

    public function consultar(string $cuit): PersonaPadron
    {
        $cuit = preg_replace('/\D/', '', $cuit) ?? '';

        if (!preg_match('/^\d{11}$/', $cuit)) {
            throw new ValidationException(['cuit' => ['El CUIT debe tener 11 dígitos.']]);
        }

        return $this->padron->consultar($cuit);
    }
}
