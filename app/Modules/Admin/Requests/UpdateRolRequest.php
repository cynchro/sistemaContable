<?php

namespace App\Modules\Admin\Requests;

use App\Support\FormRequest;

class UpdateRolRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'    => 'required|string|min:2|max:100',
            'estado'    => 'required|integer|in:0,1',
            'parent_id' => 'nullable|integer',
        ];
    }
}
