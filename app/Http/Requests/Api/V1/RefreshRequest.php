<?php

namespace App\Http\Requests\Api\V1;

class RefreshRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'refresh_token' => 'required|string',
        ];
    }
}
