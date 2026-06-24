<?php

namespace App\Modules\Honorarios\Requests;

use App\Support\FormRequest;

class UpdateServicioRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'descripcion' => 'required|string|max:255',
            'codigo'      => 'nullable|string|max:20',
            'seccion'     => 'nullable|string|max:100',
            'uc'          => 'nullable|numeric',
            'aplica_pf'   => 'nullable|in:S,N',
            'aplica_pj'   => 'nullable|in:S,N',
            'activo'      => 'nullable|in:S,N',
        ];
    }
}
