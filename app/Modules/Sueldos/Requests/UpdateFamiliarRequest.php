<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

class UpdateFamiliarRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'           => 'required|string|max:100',
            'parentesco_id'    => 'nullable|integer',
            'tipo_documento_id' => 'nullable|integer',
            'numero_documento' => 'nullable|string|max:15',
            'fecha_nacimiento' => 'nullable|date:Y-m-d',
            'escolarizado'     => 'nullable|in:S,N',
            'discapacitado'    => 'nullable|in:S,N',
            'deduce_ganancias' => 'nullable|in:S,N',
            'porc_deduccion'   => 'nullable|numeric',
        ];
    }
}
