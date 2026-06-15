<?php

namespace App\Modules\Compartido\Requests;

use App\Support\FormRequest;

class CreateCuentaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'codigo' => 'nullable|string|max:20',
        ];
    }
}
