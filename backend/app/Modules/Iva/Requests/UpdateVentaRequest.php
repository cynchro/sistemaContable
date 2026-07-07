<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

/**
 * Valida la cabecera de la venta en una edición (reemplazo completo del agregado).
 * Las líneas se validan en VentaService.
 */
class UpdateVentaRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'fecha'                   => 'required|date:Y-m-d',
            'tipo_comprobante_id'     => 'nullable|integer',
            'tipo_documento_id'       => 'nullable|integer',
            'condicion_iva_id'        => 'nullable|integer',
            'provincia_id'            => 'nullable|integer',
            'rubro_id'                => 'nullable|integer',
            'tipo_operacion_venta_id' => 'nullable|integer',
            'tipo_moneda_id'          => 'nullable|integer',
            'cliente_id'              => 'nullable|integer',
            'cliente_nombre'          => 'nullable|string|max:100',
            'cuit'                    => 'nullable|string|max:13',
            'letra'                   => 'nullable|string|max:1',
            'punto_venta'             => 'nullable|string|max:5',
            'numero'                  => 'nullable|string|max:8',
            'numero_fin'              => 'nullable|string|max:8',
            'neto_no_grav'            => 'nullable|numeric',
            'exento'                  => 'nullable|numeric',
            'imp_interno'             => 'nullable|numeric',
            'tipo_cambio'             => 'nullable|numeric',
            'concepto'                => 'nullable|integer',
            'cai'                     => 'nullable|string|max:15',
            'fecha_cai'               => 'nullable|date:Y-m-d',
            'actividad_id'            => 'nullable|integer',
            'es_bien_uso'             => 'nullable|in:S,N',
            'campo_auxiliar'          => 'nullable|string|max:255',
            'total_informado'         => 'nullable|numeric',
            'cuenta_debe_id'          => 'nullable|integer',
            'cuenta_haber_id'         => 'nullable|integer',
            'discriminaciones'        => 'nullable|array',
            'percepciones'            => 'nullable|array',
            'comprobantes_asociados'  => 'nullable|array',
        ];
    }
}
