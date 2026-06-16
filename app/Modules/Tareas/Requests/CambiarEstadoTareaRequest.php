<?php

namespace App\Modules\Tareas\Requests;

use App\Support\FormRequest;

class CambiarEstadoTareaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'estado'      => 'required|string|max:30',
            'observacion' => 'nullable|string',
        ];
    }
}
