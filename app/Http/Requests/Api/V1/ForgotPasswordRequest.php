<?php

namespace App\Http\Requests\Api\V1;

class ForgotPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}
