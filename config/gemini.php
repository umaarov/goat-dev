<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => 'gemini-2.0-flash',
//    'model' => 'gemini-1.5-pro-001',
//    'model' => 'gemini-1.5-flash-8b',
    'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
    'prompt_template' => env('GEMINI_MODERATION_PROMPT', ''),
    'banned_words_uz' => env('GEMINI_BANNED_WORDS_UZ', ''),
    'image_prompt_template' => env('GEMINI_IMAGE_MODERATION_PROMPT', ''),
    'url_prompt_template' => env('GEMINI_URL_MODERATION_PROMPT', ''),
    'use_json_mode' => env('GEMINI_USE_JSON_MODE', true),
    'context_generation_prompt' => env('GEMINI_CONTEXT_GENERATION_PROMPT', ''),
    'tag_generation_prompt' => env('GEMINI_TAG_GENERATION_PROMPT', ''),
];
