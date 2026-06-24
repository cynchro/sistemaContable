<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

class EmpresaConfigRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nro_anses'            => 'nullable|string|max:20',
            'caja'                 => 'nullable|string|max:50',
            'actividad_id'         => 'nullable|integer',
            'tipo_recibo'          => 'nullable|string|max:20',
            'lugar_pago'           => 'nullable|string|max:100',
            'jornada_horas'        => 'nullable|numeric',
            'jornada_dias'         => 'nullable|numeric',
            'ultimo_recibo'        => 'nullable|integer',
            'decimales_en_importe' => 'nullable|in:S,N',
            'exporta_conta'        => 'nullable|in:S,N',
        ];
    }
}
