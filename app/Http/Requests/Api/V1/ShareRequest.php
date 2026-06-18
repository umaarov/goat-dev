<?php

namespace App\Http\Requests\Api\V1;

class ShareRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'platform' => 'required|string|in:twitter,facebook,whatsapp,telegram,email,link_copy',
        ];
    }
}
