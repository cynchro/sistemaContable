<?php

namespace App\Modules\Compartido\Requests;

use App\Support\FormRequest;

class UpdateTipoRetencionRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'       => 'nullable|string|max:200',
            'cod_afip'     => 'nullable|string|max:10',
            'alicuota'     => 'nullable|numeric',
            'tipo_rg3685'  => 'nullable|integer',
            'provincia_id' => 'nullable|integer',
        ];
    }
}
