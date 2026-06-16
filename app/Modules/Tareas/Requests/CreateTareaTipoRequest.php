<?php

namespace App\Modules\Tareas\Requests;

use App\Support\FormRequest;

class CreateTareaTipoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'                => 'required|string|max:120',
            'categoria'             => 'nullable|string|max:80',
            'descripcion'           => 'nullable|string',
            'prioridad_sugerida'    => 'nullable|string|max:20',
            'tiempo_estimado_horas' => 'nullable|numeric',
            'activo'                => 'nullable|in:S,N',
        ];
    }
}
