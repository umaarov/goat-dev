<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', function ($attr, $value, $fail) {
                if (! $this->user()->password || ! Hash::check($value, $this->user()->password)) {
                    $fail(__('validation.current_password'));
                }
            }],
            'new_password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }
}
