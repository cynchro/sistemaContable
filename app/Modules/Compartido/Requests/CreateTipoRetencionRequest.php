<?php

namespace App\Modules\Compartido\Requests;

use App\Support\FormRequest;

class CreateTipoRetencionRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:200',
            'cod_afip'     => 'nullable|string|max:10',
            'alicuota'     => 'nullable|numeric',
            'tipo_rg3685'  => 'nullable|integer',
            'provincia_id' => 'nullable|integer',
            'base_calculo' => 'nullable|in:neto_gravado,neto_mas_imp_interno,iva_percepcion',
        ];
    }
}
