<?php

namespace App\Modules\Fiscal\Requests;

use App\Support\FormRequest;

class CreateTributoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:150',
            'parent_id'    => 'nullable|integer',
            'tipo_persona' => 'nullable|string|max:20',
            'subcategoria' => 'nullable|string|max:100',
            'is_activo'    => 'nullable|in:S,N',
        ];
    }
}
