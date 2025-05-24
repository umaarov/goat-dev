<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => 'gemini-2.0-flash',
    'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/',
    'prompt_template' => env('GEMINI_MODERATION_PROMPT', ''),
    'banned_words_uz' => env('GEMINI_BANNED_WORDS_UZ', ''),

];
