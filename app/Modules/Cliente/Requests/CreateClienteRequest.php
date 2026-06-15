<?php

namespace App\Modules\Cliente\Requests;

use App\Support\FormRequest;

class CreateClienteRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            // Regla de ejemplo (scaffolding). Añade aquí los campos de tu dominio.
            'nombre' => 'required|string|min:2|max:255',
        ];
    }
}
