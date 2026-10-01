<?php

namespace App\Http\Requests\Api\V1;

class SocialLoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Google: an OpenID Connect id_token. GitHub: an OAuth access token issued to this app.
            'token' => 'required_without_all:telegram,code|nullable|string|max:4096',
            // X: OAuth2 authorization code + PKCE verifier, exchanged server-side.
            'code' => 'required_without_all:telegram,token|nullable|string|max:2048',
            'code_verifier' => 'required_with:code|nullable|string|min:43|max:128',
            'redirect_uri' => 'required_with:code|nullable|string|max:512',
            // Telegram: the full Login Widget payload (id, hash, auth_date, ...).
            'telegram' => 'required_without_all:token,code|nullable|array',
            'device_name' => 'nullable|string|max:255',
        ];
    }
}
