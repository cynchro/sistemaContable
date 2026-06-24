<?php

namespace App\Modules\Fiscal\Requests;

use App\Support\FormRequest;

class CambiarEstadoVencimientoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'estado'        => 'required|string|max:40',
            'observaciones' => 'nullable|string',
        ];
    }
}
