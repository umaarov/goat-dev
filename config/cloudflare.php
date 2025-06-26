<?php

return [
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'api_token'  => env('CLOUDFLARE_API_TOKEN'),
    'ai_model'   => env('CLOUDFLARE_AI_MODEL', '@cf/runwayml/stable-diffusion-v1-5-img2img'),
];
