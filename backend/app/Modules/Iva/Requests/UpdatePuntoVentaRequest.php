<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

class UpdatePuntoVentaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'numero'       => 'nullable|integer',
            'descripcion'  => 'nullable|string|max:120',
            'tipo_emision' => 'nullable|in:CAE,CAEA',
            'activo'       => 'nullable|in:S,N',
        ];
    }
}
