@php use Illuminate\Support\Str; @endphp
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
                    <div id="username-status" class="mt-1 text-sm"></div> {{-- For JS validation messages --}}
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
                                 {{-- Alt text localized --}}
                                 class="h-full w-full object-cover" id="current_profile_picture_img_display"></div>
                    </div>

                    <label for="profile_picture_trigger"
                           class="relative block border border-gray-300 rounded-md p-2 cursor-pointer hover:border-blue-500">
                        <div class="flex items-center">
                            <div id="profile_picture_preview_cropper"
                                 class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3 overflow-hidden">
                                <img id="profile_picture_img_preview" src="#"
                                     alt="{{ __('messages.upload_new_pfp_label') }}" {{-- Alt text localized --}}
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
                               value="0"> {{-- Default to false if checkbox not sent --}}
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
        // Translations for username checker, ensuring keys are correctly referenced from messages.php
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 4v16m8-8H4"/>
        </svg>`;

        // DOM element references
        const removeProfilePictureCheckbox = document.getElementById('remove_profile_picture');
        const profilePictureFinalInput = document.getElementById('profile_picture_final');
        const profilePictureImgPreview = document.getElementById('profile_picture_img_preview');
        const profilePicturePlaceholderIcon = document.getElementById('profile_picture_placeholder_icon');

        // Event listener for 'remove profile picture' checkbox
        if (removeProfilePictureCheckbox && profilePictureFinalInput) {
            removeProfilePictureCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    profilePictureFinalInput.value = ''; // Clear the file input
                    const triggerInput = document.getElementById('profile_picture_trigger');
                    if (triggerInput) triggerInput.value = ''; // Reset the trigger input

                    // Reset cropper preview
                    if (profilePictureImgPreview) {
                        profilePictureImgPreview.classList.add('hidden');
                        profilePictureImgPreview.src = '#';
                    }
                    if (profilePicturePlaceholderIcon) profilePicturePlaceholderIcon.classList.remove('hidden');

                    // Destroy cropper instance if active for this input
                    if (window.currentCropperInstance && window.lastTriggeredImageInputId === 'profile_picture_trigger') {
                        window.currentCropperInstance.destroy();
                        window.currentCropperInstance = null;
                        document.getElementById('imageCropModalGlobal').classList.add('hidden');
                    }
                }
            });
        }

        // Initialize scripts on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function () {
            initUsernameChecker(); // Initialize username availability checker

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
        });

        // Function to initialize username availability checker
        function initUsernameChecker() {
            const usernameInput = document.getElementById('username');
            const debounceTimeout = 500; // milliseconds
            let typingTimer;
            let lastCheckedUsername = usernameInput.value.trim();

            const statusElement = document.getElementById('username-status');

            function checkUsername() {
                const username = usernameInput.value.trim();

                // Clear status if username is empty
                if (username === '') {
                    statusElement.textContent = '';
                    usernameInput.classList.remove('border-red-500', 'border-green-500');
                    lastCheckedUsername = '';
                    return;
                }

                // Avoid re-checking if username hasn't changed and was valid, unless an error was previously shown
                if (username === lastCheckedUsername && !usernameInput.classList.contains('border-red-500')) {
                    if (statusElement.classList.contains('text-red-600') && usernameInput.classList.contains('border-red-500')) {
                        // It was an error, so re-validate
                    } else {
                        return; // Username is unchanged and was valid
                    }
                }

                // Client-side validation rules
                const minLength = 5;
                const maxLength = 24;
                const startsWithLetter = /^[a-zA-Z]/.test(username);
                const onlyValidChars = /^[a-zA-Z0-9_-]+$/.test(username);
                const notOnlyNumbers = !/^\d+$/.test(username);
                const noConsecutiveChars = !/(.)\1{2,}/.test(username); // No more than 2 consecutive identical characters
                let errorMessage = null;

                // Check validation rules and set error message if any fails
                if (username.length < minLength) errorMessage = usernameTranslations.minLength;
                else if (username.length > maxLength) errorMessage = usernameTranslations.maxLength;
                else if (!startsWithLetter) errorMessage = usernameTranslations.startsWithLetter;
                else if (!onlyValidChars) errorMessage = usernameTranslations.onlyValidChars;
                else if (!notOnlyNumbers) errorMessage = usernameTranslations.notOnlyNumbers;
                else if (!noConsecutiveChars) errorMessage = usernameTranslations.noConsecutiveChars;

                // Display client-side validation error if any
                if (errorMessage) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = errorMessage;
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                // Show 'checking' status and make API call for server-side validation
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
                        // Update status based on API response
                        if (data.available) {
                            statusElement.className = 'mt-1 text-sm text-green-600';
                            statusElement.textContent = data.message || usernameTranslations.available;
                            usernameInput.classList.remove('border-red-500');
                            usernameInput.classList.add('border-green-500');
                            lastCheckedUsername = username; // Update last checked username on success
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
                statusElement.textContent = ''; // Clear status on typing
                usernameInput.classList.remove('border-red-500', 'border-green-500');
                typingTimer = setTimeout(checkUsername, debounceTimeout); // Debounce API calls
            });
            usernameInput.addEventListener('blur', checkUsername); // Check on blur
        }
    </script>
@endsection
