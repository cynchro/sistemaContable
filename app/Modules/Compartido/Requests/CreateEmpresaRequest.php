<?php

namespace App\Modules\Compartido\Requests;

use App\Support\FormRequest;

class CreateEmpresaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'                   => 'required|string|max:150',
            'condicion_iva_id'         => 'nullable|integer',
            'cuit'                     => 'nullable|string|max:13',
            'domicilio'                => 'nullable|string|max:150',
            'localidad'                => 'nullable|string|max:100',
            'telefono'                 => 'nullable|string|max:50',
            'ingresos_brutos'          => 'nullable|string|max:20',
            'establecimiento'          => 'nullable|string|max:12',
            'nro_libro_iva'            => 'nullable|string|max:12',
            'nro_libro_iva_ven'        => 'nullable|string|max:12',
            'fecha_inicio_actividades' => 'nullable|date:Y-m-d',
            'actividad1_id'            => 'nullable|integer',
            'actividad2_id'            => 'nullable|integer',
            'exporta'                  => 'nullable|in:S,N',
            'inactiva'                 => 'nullable|in:S,N',
        ];
    }
}
