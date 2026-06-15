<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'           => 'required|string|max:100',
            'condicion_iva_id' => 'nullable|integer',
            'provincia_id'     => 'nullable|integer',
            'cuenta_id'        => 'nullable|integer',
            'rubro_id'         => 'nullable|integer',
            'cuit'             => 'nullable|string|max:13',
            'domicilio'        => 'nullable|string|max:100',
            'localidad'        => 'nullable|string|max:50',
            'telefono'         => 'nullable|string|max:25',
            'ingresos_brutos'  => 'nullable|string|max:20',
            'esglobal'         => 'nullable|in:S,N',
        ];
    }
}
