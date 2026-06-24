<?php

namespace App\Modules\Sueldos\Requests;

use App\Support\FormRequest;

/**
 * Valida que venga la lista de novedades. Cada ítem (concepto_id, cantidad, importe)
 * se valida en profundidad en LiquidacionService.
 */
class NovedadesRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'novedades' => 'required|array',
        ];
    }
}
