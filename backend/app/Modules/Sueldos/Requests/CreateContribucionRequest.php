<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

class CreateContribucionRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'descripcion'   => 'required|string|max:100',
            'porcentaje'    => 'nullable|numeric',
            'importe_fijo'  => 'nullable|numeric',
            'incluye_norem' => 'nullable|in:S,N',
            'cuenta_id'     => 'nullable|integer',
            'orden'         => 'nullable|integer',
        ];
    }
}
