<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

/**
 * Valida la cabecera de la compra. Las líneas de discriminación (y sus retenciones)
 * se validan en profundidad en CompraService.
 */
class CreateCompraRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'fecha'                    => 'required|date:Y-m-d',
            'tipo_comprobante_id'      => 'nullable|integer',
            'condicion_iva_id'         => 'nullable|integer',
            'provincia_id'             => 'nullable|integer',
            'rubro_id'                 => 'nullable|integer',
            'tipo_operacion_compra_id' => 'nullable|integer',
            'tipo_moneda_id'           => 'nullable|integer',
            'proveedor_id'             => 'nullable|integer',
            'proveedor_nombre'         => 'nullable|string|max:100',
            'cuit'                     => 'nullable|string|max:13',
            'letra'                    => 'nullable|string|max:1',
            'punto_venta'              => 'nullable|string|max:5',
            'numero'                   => 'nullable|string|max:8',
            'neto_no_grav'             => 'nullable|numeric',
            'exento'                   => 'nullable|numeric',
            'imp_interno'              => 'nullable|numeric',
            'tipo_cambio'              => 'nullable|numeric',
            'concepto'                 => 'nullable|integer',
            'cai'                      => 'nullable|string|max:15',
            'fecha_cai'                => 'nullable|date:Y-m-d',
            'actividad_id'             => 'nullable|integer',
            'concepto_dj'              => 'nullable|integer',
            'campo_auxiliar'           => 'nullable|string|max:255',
            'total_informado'          => 'nullable|numeric',
            'discriminaciones'         => 'nullable|array',
            'percepciones'             => 'nullable|array',
        ];
    }
}
