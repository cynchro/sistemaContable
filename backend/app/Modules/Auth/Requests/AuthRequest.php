<?php

namespace App\Modules\Auth\Requests;

use App\Support\FormRequest;

class AuthRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'usuario' => 'required|email',
            'clave'   => 'required|min:6',
        ];
    }
}
