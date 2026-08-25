<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

/** El worker reporta el progreso/resultado de una liquidación que ya tomó. */
class ReportarEstadoLiquidacionRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'estado'    => 'required|string|in:en_curso,terminada,error',
            // Sin tipo forzado: el worker puede mandar un objeto (detalle agregados/errores por
            // libro) o directamente un string — el Controller lo normaliza a JSON antes de
            // persistir (columna TEXT).
            'resultado' => 'nullable',
        ];
    }
}
