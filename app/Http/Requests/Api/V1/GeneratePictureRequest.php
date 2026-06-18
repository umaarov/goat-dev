<?php

namespace App\Http\Requests\Api\V1;

class GeneratePictureRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'prompt' => 'required|string|min:10|max:350',
        ];
    }
}
