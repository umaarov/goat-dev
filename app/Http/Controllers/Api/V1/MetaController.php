<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PostMediaService;
use Illuminate\Http\JsonResponse;

class MetaController extends ApiController
{
    /**
     * GET /meta/config — static configuration the mobile client needs.
     */
    public function config(): JsonResponse
    {
        return $this->ok([
            'app_name' => config('app.name'),
            'api_version' => 'v1',
            'available_locales' => config('app.available_locales', ['en' => 'English']),
            'default_locale' => config('app.locale'),
            'limits' => [
                'post_image_max_kb' => PostMediaService::MAX_POST_IMAGE_SIZE_KB,
                'post_question_max' => 255,
                'post_option_title_max' => 40,
                'comment_max' => 1000,
                'external_links_max' => 3,
            ],
            'share_platforms' => ['twitter', 'facebook', 'whatsapp', 'telegram', 'email', 'link_copy'],
            'vote_options' => ['option_one', 'option_two'],
            'feed_filters' => ['latest', 'trending'],
        ]);
    }
}
