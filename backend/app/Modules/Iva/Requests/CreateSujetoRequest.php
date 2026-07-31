<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

/**
 * Alta de un sujeto del Padrón Único (cliente o proveedor, según la ruta). El CUIT es
 * obligatorio: es la clave del padrón (ver documentacion/pedido-padron-unico-contribuyentes.md).
 */
class CreateSujetoRequest extends FormRequest
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
            // Concepto por defecto (documento "Satélite Visual IVA" §5.2), tenant-level: a
            // diferencia de la vieja cuenta contable directa, no depende de una empresa activada.
            'concepto_default_id' => 'nullable|integer',
        ];
    }
}
