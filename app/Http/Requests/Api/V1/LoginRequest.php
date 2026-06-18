<?php

namespace App\Http\Requests\Api\V1;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'login_identifier' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ];
    }
}
