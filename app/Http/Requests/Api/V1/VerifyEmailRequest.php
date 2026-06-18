<?php

namespace App\Http\Requests\Api\V1;

class VerifyEmailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:users,id',
            'token' => 'required|string',
        ];
    }
}
