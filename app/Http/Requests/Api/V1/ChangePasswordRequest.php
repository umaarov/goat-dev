<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', function ($attr, $value, $fail) {
                if (! $this->user()->password || ! Hash::check($value, $this->user()->password)) {
                    $fail(__('validation.current_password'));
                }
            }],
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }
}
