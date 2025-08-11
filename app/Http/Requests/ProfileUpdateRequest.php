<?php

namespace App\Http\Requests;

use App\Services\ModerationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $headerTemplateKeys = array_keys(Config::get('app.header_templates', []));
        $availableLocaleKeys = array_keys(Config::get('app.available_locales', []));

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'username' => [
                'required', 'string', 'min:5', 'max:24', 'alpha_dash',
                Rule::unique('users')->ignore($user->id),
                'regex:/^[a-zA-Z]/',
            ],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_profile_picture' => ['nullable', 'boolean'],
            'header_background_upload' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'header_background_template' => ['nullable', 'string', Rule::in($headerTemplateKeys)],
            'remove_header_background' => ['nullable', 'boolean'],
            'show_voted_posts_publicly' => ['required', 'boolean'],
            'receives_notifications' => ['required', 'boolean'],
            'ai_insight_preference' => ['required', 'string', Rule::in(['expanded', 'less', 'hidden'])],
            'locale' => ['nullable', 'string', Rule::in($availableLocaleKeys)],
            'external_links' => ['nullable', 'array', 'max:3'],
            'external_links.*' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function after(ModerationService $moderationService, $validator)
    {
        $user = $this->user();
        $moderationLanguageCode = $user->locale ?? Config::get('app.locale');

        $textFieldsToModerate = ['first_name', 'last_name', 'username'];
        foreach ($textFieldsToModerate as $field) {
            if ($this->filled($field) && $this->input($field) !== $user->{$field}) {
                $moderationResult = $moderationService->moderateText($this->input($field), $moderationLanguageCode);
                if (!$moderationResult['is_appropriate']) {
                    $validator->errors()->add($field, $moderationResult['reason'] ?: 'This content is inappropriate.');
                }
            }
        }

        if ($this->hasFile('profile_picture')) {
            $imageModeration = $moderationService->moderateImage($this->file('profile_picture'), $moderationLanguageCode);
            if (!$imageModeration['is_appropriate']) {
                $validator->errors()->add('profile_picture', $imageModeration['reason'] ?: 'This image is inappropriate.');
            }
        }

    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'show_voted_posts_publicly' => $this->boolean('show_voted_posts_publicly'),
            'receives_notifications' => $this->boolean('receives_notifications'),
            'remove_profile_picture' => $this->boolean('remove_profile_picture'),
            'remove_header_background' => $this->boolean('remove_header_background'),
        ]);
    }
}
