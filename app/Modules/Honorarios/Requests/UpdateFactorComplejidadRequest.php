<?php

namespace App\Modules\Honorarios\Requests;

use App\Support\FormRequest;

class UpdateFactorComplejidadRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nivel'  => 'required|string|max:30',
            'factor' => 'nullable|numeric',
            'label'  => 'nullable|string|max:60',
            'orden'  => 'nullable|integer',
        ];
    }
}
