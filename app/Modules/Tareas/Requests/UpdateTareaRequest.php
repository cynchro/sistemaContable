<?php

namespace App\Modules\Tareas\Requests;

use App\Support\FormRequest;

class UpdateTareaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'titulo'       => 'required|string|max:150',
            'tipo_id'      => 'nullable|integer',
            'empresa_id'   => 'nullable|integer',
            'descripcion'  => 'nullable|string',
            'prioridad'    => 'nullable|string|max:20',
            'fecha_limite' => 'nullable|date:Y-m-d',
            'assigned_to'  => 'nullable|integer',
        ];
    }
}
