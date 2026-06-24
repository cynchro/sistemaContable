<?php

namespace App\Modules\Compartido\Requests;

use App\Support\FormRequest;

class UpdateRubroRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre' => 'required|string|max:300',
            'codigo' => 'nullable|string|max:7',
        ];
    }
}
