@php use Illuminate\Support\Facades\Storage;use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', __('messages.edit_profile_title'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            {{-- Page heading, localized. --}}
            <h2 class="text-2xl font-semibold mb-6 text-blue-800">{{ __('messages.edit_profile_title') }}</h2>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Personal Information Section --}}
                <div class="mb-4">
                    <label for="first_name"
                           class="block text-gray-700 mb-2">{{ __('messages.first_name_label') }}</label>
                    <input id="first_name" type="text" name="first_name"
                           value="{{ old('first_name', $user->first_name) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('first_name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="last_name" class="block text-gray-700 mb-2">{{ __('messages.last_name_label') }}</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('last_name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-gray-700 mb-2">{{ __('messages.username_label') }}</label>
                    <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    <div id="username-status" class="mt-1 text-sm"></div>
                    @error('username')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Profile Picture Section --}}
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2">{{ __('messages.update_pfp_label') }}</label>
                    @php
                        $profilePic = $user->profile_picture
                            ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                                ? $user->profile_picture
                                : Storage::url($user->profile_picture))
                            : asset('images/default-pfp.png');
                    @endphp

                    <div class="flex items-center mb-3">
                        <span class="mr-2 text-sm text-gray-600">{{ __('messages.current_pfp_label') }}</span>
                        <div id="profile_picture_current_container"
                             class="h-16 w-16 rounded-full overflow-hidden border border-gray-200">
                            <img src="{{ $profilePic }}" alt="{{ __('messages.current_pfp_label') }}"
                                 class="h-full w-full object-cover" id="current_profile_picture_img_display"></div>
                    </div>

                    <label for="profile_picture_trigger"
                           class="relative block border border-gray-300 rounded-md p-2 cursor-pointer hover:border-blue-500">
                        <div class="flex items-center">
                            <div id="profile_picture_preview_cropper"
                                 class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3 overflow-hidden">
                                <img id="profile_picture_img_preview" src="#"
                                     alt="{{ __('messages.upload_new_pfp_label') }}"
                                     class="w-10 h-10 rounded-full object-cover hidden">
                                <svg id="profile_picture_placeholder_icon" xmlns="http://www.w3.org/2000/svg"
                                     class="h-6 w-6 text-gray-600" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">{{ __('messages.upload_new_pfp_label') }}</span>
                                    <span
                                        class="text-sm text-blue-800 hover:underline">{{ __('messages.choose_file_button') }}</span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <input id="profile_picture_trigger" type="file" class="hidden"
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                           onchange="openImageCropper(event, 'profile_picture_final', 'profile_picture_img_preview', 'profile_picture_placeholder_icon', 'profile_picture_preview_cropper')">
                    <input id="profile_picture_final" type="file" name="profile_picture" class="hidden">

                    @error('profile_picture')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror

                    <div class="mt-3 mb-2">
                        <label for="remove_profile_picture"
                               class="flex items-center text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="remove_profile_picture" name="remove_profile_picture" value="1"
                                   class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span>{{ __('messages.revert_to_initials_pfp_label') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Privacy Settings Section --}}
                <div class="mb-6 pt-4 border-t border-gray-200">
                    <label
                        class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.privacy_settings_label') }}</label>
                    <div class="flex items-center">
                        <input type="hidden" name="show_voted_posts_publicly"
                               value="0">
                        <input type="checkbox" id="show_voted_posts_publicly" name="show_voted_posts_publicly" value="1"
                               class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                               @if(old('show_voted_posts_publicly', $user->show_voted_posts_publicly ?? true)) checked @endif>
                        <label for="show_voted_posts_publicly"
                               class="ml-2 text-sm text-gray-700">{{ __('messages.show_voted_publicly_label') }}</label>
                    </div>
                    @error('show_voted_posts_publicly')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">{{ __('messages.show_voted_publicly_note') }}</p>
                </div>

                {{-- Language Settings Section --}}
                <div class="mb-6 pt-4 border-t border-gray-200">
                    <h3 class="text-md font-semibold text-gray-700 mb-2">{{ __('messages.language_settings_label') }}</h3>
                    <div>
                        <label for="locale"
                               class="block text-sm text-gray-700 mb-1">{{ __('messages.select_language_label') }}</label>
                        <select id="locale" name="locale"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            @if(isset($available_locales) && is_array($available_locales))
                                @foreach($available_locales as $localeKey => $localeName)
                                    <option
                                        value="{{ $localeKey }}" {{ (old('locale', $user->locale ?? $current_locale) == $localeKey) ? 'selected' : '' }}>
                                        {{ $localeName }} {{-- Assuming $localeName is already localized or is the native name --}}
                                    </option>
                                @endforeach
                            @else
                                <option value="en" selected>English
                                </option> {{-- Fallback, consider localizing "English" if needed --}}
                            @endif
                        </select>
                        @error('locale')
                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                {{-- External Links Section START --}}
                <div class="mb-6 pt-4 border-t border-gray-200">
                    <h3 class="text-md font-semibold text-gray-700 mb-2">{{ __('messages.external_links_label') }}</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ __('messages.external_links_description') }}</p>

                    @for ($i = 0; $i < 3; $i++)
                        <div class="mb-3 relative">
                            <label for="external_link_{{ $i }}"
                                   class="sr-only">{{ __('messages.external_link_label', ['number' => $i + 1]) }}</label>
                            <div class="flex items-center">
                                <span
                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"
                                    id="icon_container_external_link_{{ $i }}">
                                    {{-- Default Link Icon (generic) --}}
                                    <svg class="h-4 w-4" id="icon_external_link_{{ $i }}" fill="none"
                                         viewBox="0 0 24 24">
                                        <path
                                            d="M13.0601 10.9399C15.3101 13.1899 15.3101 16.8299 13.0601 19.0699C10.8101 21.3099 7.17009 21.3199 4.93009 19.0699C2.69009 16.8199 2.68009 13.1799 4.93009 10.9399"
                                            stroke="#292D32" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round"/>
<path
    d="M10.59 13.4099C8.24996 11.0699 8.24996 7.26988 10.59 4.91988C12.93 2.56988 16.73 2.57988 19.08 4.91988C21.43 7.25988 21.42 11.0599 19.08 13.4099"
    stroke="#292D32" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>

                                    </svg>
                                </span>
                                <input id="external_link_{{ $i }}" type="url" name="external_links[]"
                                       value="{{ old('external_links.' . $i, ($user->external_links[$i] ?? null) ?: '') }}"
                                       placeholder="{{ __('messages.external_link_placeholder') }}"
                                       class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       oninput="updateDynamicLinkIcon(this, 'icon_container_external_link_{{ $i }}')">
                            </div>
                            @error('external_links.' . $i)
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    @endfor
                    @error('external_links') {{-- For array-level errors like max items --}}
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>
                {{-- External Links Section END --}}

                <div class="flex items-center justify-between mt-6">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.update_profile_button') }}
                    </button>
                    <a href="{{ route('profile.show', $user->username) }}"
                       class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.cancel_button') }}
                    </a>
                </div>
            </form>

            @if($user->password)
                <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between">
                    <a href="{{ route('password.change.form') }}" class="text-blue-800 hover:underline">
                        {{ __('messages.change_password_link') }}
                    </a>

                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-red-600 hover:underline cursor-pointer">
                            {{ __('messages.logout_button') }}
                        </button>
                    </form>
                </div>
            @elseif($user->google_id)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-gray-600 text-sm">
                        {{ __('messages.password_not_available_google') }}
                    </p>
                </div>
            @endif

        </div>
    </div>

    <script>
        const usernameTranslations = {
            checking: @json(__('messages.username_availability_checking')),
            available: @json(__('messages.username_available')),
            taken: @json(__('messages.username_taken')),
            couldNotVerify: @json(__('messages.username_could_not_verify')),
            minLength: @json(__('messages.username_min_length')),
            maxLength: @json(__('messages.username_max_length')),
            startsWithLetter: @json(__('messages.username_startsWithLetter')),
            onlyValidChars: @json(__('messages.username_onlyValidChars')),
            notOnlyNumbers: @json(__('messages.username_notOnlyNumbers')),
            noConsecutiveChars: @json(__('messages.username_noConsecutiveChars'))
        };

        // Default SVG for image placeholder (no text to translate here)
        const defaultPreviewIconSVG = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none"
             viewBox="0 0 24 24" stroke="currentColor">
                                                     <path
                                            d="M13.0601 10.9399C15.3101 13.1899 15.3101 16.8299 13.0601 19.0699C10.8101 21.3099 7.17009 21.3199 4.93009 19.0699C2.69009 16.8199 2.68009 13.1799 4.93009 10.9399"
                                            stroke="#292D32" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round"/>
<path
    d="M10.59 13.4099C8.24996 11.0699 8.24996 7.26988 10.59 4.91988C12.93 2.56988 16.73 2.57988 19.08 4.91988C21.43 7.25988 21.42 11.0599 19.08 13.4099"
    stroke="#292D32" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>

            </svg>`;

        // DOM element references
        const removeProfilePictureCheckbox = document.getElementById('remove_profile_picture');
        const profilePictureFinalInput = document.getElementById('profile_picture_final');
        const profilePictureImgPreview = document.getElementById('profile_picture_img_preview');
        const profilePicturePlaceholderIcon = document.getElementById('profile_picture_placeholder_icon');

        if (removeProfilePictureCheckbox && profilePictureFinalInput) {
            removeProfilePictureCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    profilePictureFinalInput.value = '';
                    const triggerInput = document.getElementById('profile_picture_trigger');
                    if (triggerInput) triggerInput.value = '';

                    if (profilePictureImgPreview) {
                        profilePictureImgPreview.classList.add('hidden');
                        profilePictureImgPreview.src = '#';
                    }
                    if (profilePicturePlaceholderIcon) profilePicturePlaceholderIcon.classList.remove('hidden');

                    if (window.currentCropperInstance && window.lastTriggeredImageInputId === 'profile_picture_trigger') {
                        window.currentCropperInstance.destroy();
                        window.currentCropperInstance = null;
                        document.getElementById('imageCropModalGlobal').classList.add('hidden');
                    }
                }
            });
        }

        // START: External Link Icon Logic
        const SvgIconCollection = {
            telegram: '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.2,4.4L2.9,10.7c-1.1,0.4-1.1,1.1-0.2,1.3l4.1,1.3l1.6,4.8c0.2,0.5,0.1,0.7,0.6,0.7c0.4,0,0.6-0.2,0.8-0.4c0.1-0.1,1-1,2-2l4.2,3.1c0.8,0.4,1.3,0.2,1.5-0.7l2.8-13.1C20.6,4.6,19.9,4,19.2,4.4z M17.1,7.4l-7.8,7.1L9,17.8L7.4,13l9.2-5.8C17,6.9,17.4,7.1,17.1,7.4z"/></svg>',
            twitter: '<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            instagram: '<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.316.011 7.053.069 2.59.284.287 2.59.07 7.053.011 8.316 0 8.741 0 12c0 3.259.011 3.684.069 4.947.217 4.46 2.522 6.769 7.053 6.984 1.267.058 1.692.069 4.947.069 3.259 0 3.684-.011 4.947-.069 4.46-.217 6.769-2.522 6.984-7.053.058-1.267.069-1.692.069-4.947 0-3.259-.011-3.684-.069-4.947-.217-4.46-2.522-6.769-7.053-6.984C15.684.011 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
            facebook: '<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12c6.627 0 12-5.373 12-12S18.627 0 12 0zm3.055 8.181h-1.717c-.594 0-.708.282-.708.695v.978h2.399l-.311 2.445h-2.088V20.5h-2.523v-8.199H8.222V9.854h1.887V8.69c0-1.871 1.142-2.89 2.813-2.89a15.868 15.868 0 011.67.087v2.204h-.986c-.908 0-1.084.432-1.084 1.065v.025z"/></svg>',
            linkedin: '<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/></svg>',
            github: '<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.026 2.747-1.026.546 1.379.201 2.398.098 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.922.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.001 10.001 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>',
            default: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"><path d="M13.0601 10.9399C15.3101 13.1899 15.3101 16.8299 13.0601 19.0699C10.8101 21.3099 7.17009 21.3199 4.93009 19.0699C2.69009 16.8199 2.68009 13.1799 4.93009 10.9399" stroke="#292D32" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>  <path d="M10.59 13.4099C8.24996 11.0699 8.24996 7.26988 10.59 4.91988C12.93 2.56988 16.73 2.57988 19.08 4.91988C21.43 7.25988 21.42 11.0599 19.08 13.4099" stroke="#292D32" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        };

        function getDomainIconSvg(url) {
            try {
                const rawHostname = new URL(url).hostname;

                if (!rawHostname || typeof rawHostname !== 'string') {
                    return SvgIconCollection.default;
                }
                const hostname = rawHostname.toLowerCase();

                const checkDomain = (targetDomain) => {
                    if (hostname === targetDomain) {
                        return true;
                    }
                    return hostname.endsWith('.' + targetDomain);

                };

                if (checkDomain('t.me') || checkDomain('telegram.me')) {
                    return SvgIconCollection.telegram;
                }
                if (checkDomain('twitter.com') || checkDomain('x.com')) {
                    return SvgIconCollection.twitter;
                }
                if (checkDomain('instagram.com')) {
                    return SvgIconCollection.instagram;
                }
                if (checkDomain('facebook.com')) {
                    return SvgIconCollection.facebook;
                }
                if (checkDomain('linkedin.com')) {
                    return SvgIconCollection.linkedin;
                }
                if (checkDomain('github.com')) {
                    return SvgIconCollection.github;
                }

                return SvgIconCollection.default;
            } catch (e) {
                return SvgIconCollection.default;
            }
        }

        function updateDynamicLinkIcon(inputElement, iconContainerId) {
            const iconContainer = document.getElementById(iconContainerId);
            if (iconContainer) {
                iconContainer.innerHTML = getDomainIconSvg(inputElement.value);
            }
        }

        // END: External Link Icon Logic

        document.addEventListener('DOMContentLoaded', function () {
            initUsernameChecker();

            const profilePictureTriggerInput = document.getElementById('profile_picture_trigger');

            // Event listener for profile picture trigger input
            if (profilePictureTriggerInput && removeProfilePictureCheckbox) {
                profilePictureTriggerInput.addEventListener('change', function () {
                    // When a new file is selected, uncheck "revert to initials"
                    if (this.files && this.files.length > 0) {
                        removeProfilePictureCheckbox.checked = false;
                    }
                });
            }

            for (let i = 0; i < 3; i++) {
                const inputElement = document.getElementById(`external_link_${i}`);
                if (inputElement && inputElement.value) {
                    updateDynamicLinkIcon(inputElement, `icon_container_external_link_${i}`);
                }
            }
        });

        // Function to initialize username availability checker
        function initUsernameChecker() {
            const usernameInput = document.getElementById('username');
            const debounceTimeout = 500;
            let typingTimer;
            let lastCheckedUsername = usernameInput.value.trim();

            const statusElement = document.getElementById('username-status');

            function checkUsername() {
                const username = usernameInput.value.trim();

                if (username === '') {
                    statusElement.textContent = '';
                    usernameInput.classList.remove('border-red-500', 'border-green-500');
                    lastCheckedUsername = '';
                    return;
                }

                if (username === lastCheckedUsername && !usernameInput.classList.contains('border-red-500')) {
                    if (statusElement.classList.contains('text-red-600') && usernameInput.classList.contains('border-red-500')) {
                    } else {
                        return;
                    }
                }

                const minLength = 5;
                const maxLength = 24;
                const startsWithLetter = /^[a-zA-Z]/.test(username);
                const onlyValidChars = /^[a-zA-Z0-9_-]+$/.test(username);
                const notOnlyNumbers = !/^\d+$/.test(username);
                const noConsecutiveChars = !/(.)\1{2,}/.test(username);
                let errorMessage = null;

                if (username.length < minLength) errorMessage = usernameTranslations.minLength;
                else if (username.length > maxLength) errorMessage = usernameTranslations.maxLength;
                else if (!startsWithLetter) errorMessage = usernameTranslations.startsWithLetter;
                else if (!onlyValidChars) errorMessage = usernameTranslations.onlyValidChars;
                else if (!notOnlyNumbers) errorMessage = usernameTranslations.notOnlyNumbers;
                else if (!noConsecutiveChars) errorMessage = usernameTranslations.noConsecutiveChars;

                if (errorMessage) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = errorMessage;
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                statusElement.className = 'mt-1 text-sm text-gray-500';
                statusElement.textContent = usernameTranslations.checking;

                fetch('{{ route("check.username") }}?username=' + encodeURIComponent(username), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            statusElement.className = 'mt-1 text-sm text-green-600';
                            statusElement.textContent = data.message || usernameTranslations.available;
                            usernameInput.classList.remove('border-red-500');
                            usernameInput.classList.add('border-green-500');
                            lastCheckedUsername = username;
                        } else {
                            statusElement.className = 'mt-1 text-sm text-red-600';
                            statusElement.textContent = data.message || usernameTranslations.taken;
                            usernameInput.classList.remove('border-green-500');
                            usernameInput.classList.add('border-red-500');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking username:', error);
                        statusElement.className = 'mt-1 text-sm text-gray-500';
                        statusElement.textContent = usernameTranslations.couldNotVerify;
                        usernameInput.classList.remove('border-green-500', 'border-red-500');
                    });
            }

            usernameInput.addEventListener('input', function () {
                clearTimeout(typingTimer);
                statusElement.textContent = '';
                usernameInput.classList.remove('border-red-500', 'border-green-500');
                typingTimer = setTimeout(checkUsername, debounceTimeout);
            });
            usernameInput.addEventListener('blur', checkUsername);
        }
    </script>
@endsection
