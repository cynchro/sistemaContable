<?php

namespace App\Modules\Fiscal\Requests;

use App\Support\FormRequest;

class UpdateRequerimientoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'requerimiento'      => 'required|string',
            'ente'               => 'nullable|string|max:60',
            'formulario'         => 'nullable|string|max:60',
            'nro'                => 'nullable|string|max:60',
            'oi_caso_nro'        => 'nullable|string|max:60',
            'agente'             => 'nullable|string|max:100',
            'fecha_notificacion' => 'nullable|date:Y-m-d',
            'plazo_dias'         => 'nullable|integer',
            'fecha_prorroga'     => 'nullable|date:Y-m-d',
            'fecha_cumplimiento' => 'nullable|date:Y-m-d',
            'fecha_presentacion' => 'nullable|date:Y-m-d',
            'estado'             => 'nullable|string|max:30',
            'observaciones'      => 'nullable|string',
        ];
    }
}
