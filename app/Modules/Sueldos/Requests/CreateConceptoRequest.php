<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

class CreateConceptoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'codigo'              => 'required|string|max:10',
            'descripcion'         => 'required|string|max:150',
            'formula'             => 'nullable|string|max:500',
            'tipo'                => 'nullable|integer',
            'titulo_impresion'    => 'nullable|string|max:150',
            'imprimir'            => 'nullable|in:S,N',
            'cuenta_id'           => 'nullable|integer',
            'condicion_ganancias' => 'nullable|in:S,N',
            'retencion_ganancias' => 'nullable|in:S,N',
            'exporta_a_afip'      => 'nullable|in:S,N',
            'orden'               => 'nullable|integer',
        ];
    }
}
