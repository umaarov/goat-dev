<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PostMediaService;

class UpdatePostRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $max = PostMediaService::MAX_POST_IMAGE_SIZE_KB;

        return [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_two_title' => 'required|string|max:40',
            'option_one_image' => "nullable|image|mimes:jpeg,png,jpg,gif,webp|max:{$max}",
            'option_two_image' => "nullable|image|mimes:jpeg,png,jpg,gif,webp|max:{$max}",
            'remove_option_one_image' => 'nullable|boolean',
            'remove_option_two_image' => 'nullable|boolean',
        ];
    }
}
