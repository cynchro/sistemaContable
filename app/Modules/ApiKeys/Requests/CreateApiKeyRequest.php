<?php

namespace App\Modules\ApiKeys\Requests;

use App\Support\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'name'   => 'required|string|max:255',
            'scopes' => 'nullable|array',
        ];
    }
}
