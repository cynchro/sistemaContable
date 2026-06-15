<?php

namespace App\Modules\Admin\Requests;

use App\Support\FormRequest;

class UpdatePermisoRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'key'         => 'required|string|min:2|max:100',
            'descripcion' => 'required|string|min:2|max:255',
            'estado'      => 'required|integer|in:0,1,2',
        ];
    }
}
