<?php

namespace App\Modules\Cliente\Requests;

use App\Support\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            // PUT reemplaza el recurso (scaffolding). Refleja aquí tus columnas.
            'nombre' => 'required|string|min:2|max:255',
        ];
    }
}
