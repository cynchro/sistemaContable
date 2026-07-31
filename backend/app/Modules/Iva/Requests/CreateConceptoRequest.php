<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

class CreateConceptoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre' => 'required|string|max:120',
        ];
    }
}
