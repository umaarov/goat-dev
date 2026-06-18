<?php

namespace App\Http\Requests\Api\V1;

class UpdateCommentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000',
        ];
    }
}
