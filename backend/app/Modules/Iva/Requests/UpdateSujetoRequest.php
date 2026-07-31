<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

class UpdateSujetoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'           => 'required|string|max:100',
            'cuit'             => 'required|string|max:13',
            'condicion_iva_id' => 'nullable|integer',
            'provincia_id'     => 'nullable|integer',
            'domicilio'        => 'nullable|string|max:100',
            'localidad'        => 'nullable|string|max:50',
            'telefono'         => 'nullable|string|max:25',
            'ingresos_brutos'  => 'nullable|string|max:20',
            'cp'               => 'nullable|string|max:8',
            'cai'              => 'nullable|string|max:15',
            'fecha_cai'        => 'nullable|date:Y-m-d',
            'cais'             => 'nullable|array',
            // Cuenta contable por defecto para este sujeto en esta empresa (documento "Satélite
            // Visual IVA" §5) — vive en iva_sujeto_empresas, no en el padrón (iva_sujetos), por
            // eso solo se acepta acá y no en el alta (todavía no hay empresa+sujeto activados).
            'cuenta_id'        => 'nullable|integer',
        ];
    }
}
