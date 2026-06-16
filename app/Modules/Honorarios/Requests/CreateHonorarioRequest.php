<?php

namespace App\Modules\Honorarios\Requests;

use App\Support\FormRequest;

class CreateHonorarioRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'valor_uc'    => 'nullable|numeric',
            'fecha'       => 'nullable|date:Y-m-d',
            'descripcion' => 'nullable|string|max:255',
            'lineas'      => 'nullable|array',
        ];
    }
}
