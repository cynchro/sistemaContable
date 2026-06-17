<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

/** Valida el destino al mover un comprobante (venta o compra) a otro período. */
class MoverComprobanteRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'periodo_destino_id' => 'required|integer',
        ];
    }
}
