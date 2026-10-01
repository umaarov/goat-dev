<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rules\Password;

class SetPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }
}
