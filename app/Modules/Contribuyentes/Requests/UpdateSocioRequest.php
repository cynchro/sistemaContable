<?php

namespace App\Modules\Contribuyentes\Requests;

use App\Support\FormRequest;

class UpdateSocioRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'               => 'required|string|max:150',
            'tipo_persona'         => 'nullable|string|max:20',
            'cuit'                 => 'nullable|string|max:13',
            'contacto'             => 'nullable|string|max:150',
            'telefono'             => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:100',
            'categoria'            => 'nullable|string|max:80',
            'cargo'                => 'nullable|string|max:80',
            'fecha_ingreso'        => 'nullable|date:Y-m-d',
            'participacion'        => 'nullable|numeric',
            'cliente'              => 'nullable|in:S,N',
            'colaborador'          => 'nullable|string|max:120',
            'colaborador_telefono' => 'nullable|string|max:50',
            'colaborador_email'    => 'nullable|email|max:100',
            'observacion'          => 'nullable|string',
            'is_activo'            => 'nullable|in:S,N',
        ];
    }
}
