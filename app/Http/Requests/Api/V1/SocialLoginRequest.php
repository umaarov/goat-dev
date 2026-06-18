<?php

namespace App\Http\Requests\Api\V1;

class SocialLoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Google: an OpenID Connect id_token. X / GitHub: an OAuth access token.
            'token' => 'required_without:telegram|nullable|string',
            // Telegram: the full Login Widget payload (id, hash, auth_date, ...).
            'telegram' => 'required_without:token|nullable|array',
            'device_name' => 'nullable|string|max:255',
        ];
    }
}
