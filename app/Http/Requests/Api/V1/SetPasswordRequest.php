<?php

namespace App\Http\Requests\Api\V1;

class SetPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
