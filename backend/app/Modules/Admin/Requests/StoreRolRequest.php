<?php

namespace App\Modules\Admin\Requests;

use App\Support\FormRequest;

class StoreRolRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nombre'    => 'required|string|min:2|max:100',
            'parent_id' => 'nullable|integer',
        ];
    }
}
