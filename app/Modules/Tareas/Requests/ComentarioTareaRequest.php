<?php

namespace App\Modules\Tareas\Requests;

use App\Support\FormRequest;

class ComentarioTareaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'comentario' => 'required|string',
        ];
    }
}
