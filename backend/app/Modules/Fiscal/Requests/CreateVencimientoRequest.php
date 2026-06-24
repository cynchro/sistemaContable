<?php

namespace App\Modules\Fiscal\Requests;

use App\Support\FormRequest;

class CreateVencimientoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'titulo'            => 'required|string|max:150',
            'agencia'           => 'nullable|string|max:30',
            'jurisdiccion'      => 'nullable|string|max:50',
            'tributos'          => 'nullable|array',
            'descripcion'       => 'nullable|string|max:255',
            'fecha_vencimiento' => 'nullable|date:Y-m-d',
            'observaciones'     => 'nullable|string',
            'is_activo'         => 'nullable|in:S,N',
        ];
    }
}
