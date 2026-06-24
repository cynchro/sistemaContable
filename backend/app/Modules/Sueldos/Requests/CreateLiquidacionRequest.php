<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

class CreateLiquidacionRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'periodo_liquidado' => 'required|string|max:20',
            'descripcion'       => 'nullable|string|max:150',
            'tipo'              => 'nullable|integer',
            'fecha_desde'       => 'nullable|date:Y-m-d',
            'fecha_hasta'       => 'nullable|date:Y-m-d',
            'fecha_pago'        => 'nullable|date:Y-m-d',
            'activa'            => 'nullable|in:S,N',
            'bloqueada'         => 'nullable|in:S,N',
        ];
    }
}
