<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $localeKeys = array_keys(Config::get('app.available_locales', ['en' => 'English']));

        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => [
                'sometimes', 'required', 'string', 'min:5', 'max:24', 'alpha_dash',
                Rule::unique('users')->ignore($this->user()->id),
                'regex:/^[a-zA-Z][a-zA-Z0-9_-]*$/', 'not_regex:/^\d+$/', 'not_regex:/(.)\1{3,}/',
            ],
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_profile_picture' => 'nullable|boolean',
            'header_background_upload' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'header_background_template' => ['nullable', 'string', 'regex:/^template_([1-9]|1[0-2])\.jpg$/'],
            'remove_header_background' => 'nullable|boolean',
            'show_voted_posts_publicly' => 'sometimes|boolean',
            'receives_notifications' => 'sometimes|boolean',
            'ai_insight_preference' => ['sometimes', 'string', Rule::in(['expanded', 'less', 'hidden'])],
            'locale' => ['nullable', Rule::in($localeKeys)],
            'external_links' => 'nullable|array|max:3',
            'external_links.*' => ['nullable', 'url', 'max:255', function ($attr, $value, $fail) {
                if (! empty($value) && ! Str::startsWith($value, ['http://', 'https://'])) {
                    $fail(__('messages.external_link_invalid_url_error'));
                }
            }],
        ];
    }
}
