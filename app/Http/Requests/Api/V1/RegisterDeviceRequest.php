<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required|string|max:500',
            'platform' => ['nullable', 'string', Rule::in(['ios', 'android', 'web'])],
        ];
    }
}
