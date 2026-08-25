<?php

namespace App\Modules\Iva\Requests;

use App\Support\FormRequest;

class CreateLiquidacionRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'direccion' => 'required|string|in:traer,subir,ambos',
            'libro'     => 'required|string|in:ventas,compras,ambos',
        ];
    }
}
