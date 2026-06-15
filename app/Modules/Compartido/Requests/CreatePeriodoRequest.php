<?php

namespace App\Modules\Compartido\Requests;

use App\Support\FormRequest;

class CreatePeriodoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'    => 'required|string|max:50',
            'fecha_ini' => 'required|date:Y-m-d',
            'fecha_fin' => 'required|date:Y-m-d',
        ];
    }
}
