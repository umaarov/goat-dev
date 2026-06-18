<?php

namespace App\Http\Requests\Api\V1;

class VoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'option' => 'required|in:option_one,option_two',
        ];
    }
}
