<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PostMediaService;

class StorePostRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $max = PostMediaService::MAX_POST_IMAGE_SIZE_KB;

        return [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_one_image' => "required|image|mimes:jpeg,png,jpg,webp|max:{$max}",
            'option_two_title' => 'required|string|max:40',
            'option_two_image' => "required|image|mimes:jpeg,png,jpg,webp|max:{$max}",
        ];
    }
}
