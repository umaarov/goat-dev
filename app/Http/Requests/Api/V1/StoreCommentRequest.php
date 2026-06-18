<?php

namespace App\Http\Requests\Api\V1;

class StoreCommentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ];
    }
}
