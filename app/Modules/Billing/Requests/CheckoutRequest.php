<?php

namespace App\Modules\Billing\Requests;

use App\Support\FormRequest;

class CheckoutRequest extends FormRequest
{
    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'plan' => 'required|string|max:60',
        ];
    }
}
