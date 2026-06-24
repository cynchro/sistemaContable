<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

class CreateEmpleadoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombres'                   => 'required|string|max:100',
            'primer_apellido'           => 'nullable|string|max:60',
            'segundo_apellido'          => 'nullable|string|max:60',
            'legajo'                    => 'nullable|integer',
            'cuil'                      => 'nullable|string|max:13',
            'tipo_documento_id'         => 'nullable|integer',
            'numero_documento'          => 'nullable|string|max:15',
            'fecha_nacimiento'          => 'nullable|date:Y-m-d',
            'genero'                    => 'nullable|string|max:1',
            'estado_civil_id'           => 'nullable|integer',
            'nacionalidad_id'           => 'nullable|integer',
            'direccion'                 => 'nullable|string|max:150',
            'localidad'                 => 'nullable|string|max:100',
            'provincia_id'              => 'nullable|integer',
            'telefono'                  => 'nullable|string|max:25',
            'celular'                   => 'nullable|string|max:25',
            'email'                     => 'nullable|email|max:100',
            'fecha_ingreso'             => 'nullable|date:Y-m-d',
            'fecha_egreso'              => 'nullable|date:Y-m-d',
            'categoria_id'              => 'nullable|integer',
            'basico'                    => 'nullable|numeric',
            'obra_social_id'            => 'nullable|integer',
            'regimen_jubilatorio_id'    => 'nullable|integer',
            'modalidad_contratacion_id' => 'nullable|integer',
            'situacion_revista_id'      => 'nullable|integer',
            'condicion_laboral_id'      => 'nullable|integer',
            'departamento_id'           => 'nullable|integer',
            'lugar_pago'                => 'nullable|string|max:100',
            'forma_de_pago'             => 'nullable|string|max:30',
            'numero_cuenta'             => 'nullable|string|max:30',
            'activo'                    => 'nullable|in:S,N',
        ];
    }
}
