<?php

namespace App\Modules\Contribuyentes\Requests;

use App\Support\FormRequest;

class CreateCredencialRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'tipo'          => 'nullable|in:fiscal,tarjeta',
            'sistema'       => 'required|string|max:60',
            'usuario'       => 'nullable|string|max:150',
            'clave'         => 'nullable|string|max:255',
            'estado'        => 'nullable|in:activa,inactiva,bloqueada,cerrada',
            'observaciones' => 'nullable|string',
        ];
    }
}
