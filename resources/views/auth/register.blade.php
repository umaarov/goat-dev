@extends('layouts.app')

@section('title', __('messages.register'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6 relative ">
            @if(isset($available_locales) && is_array($available_locales) && count($available_locales) > 1)
                <div class="absolute top-6 right-6 z-auto">
                    <div class="relative">
                        <select onchange="window.location.href=this.value;"
                                aria-label="{{ __('messages.select_language_label') ?? 'Select Language' }}"
                                class="block appearance-none w-auto bg-white border border-gray-300 hover:border-gray-400 px-3 py-1.5 pr-7 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs text-gray-700"> {{-- Compact styling --}}
                            @foreach($available_locales as $localeKey => $localeName)
                                <option
                                    value="{{ route('language.set', $localeKey) }}" {{ ($current_locale ?? app()->getLocale()) == $localeKey ? 'selected' : '' }}>
                                    {{ $localeName }}
                                </option>
                            @endforeach
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 text-gray-700">
                            <svg class="fill-current h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg> {{-- Smaller icon --}}
                        </div>
                    </div>
                </div>
            @endif

            <h2 class="text-2xl font-semibold mb-4 text-blue-800">{{ __('messages.register') }}</h2>
            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label for="first_name"
                           class="block text-gray-700 mb-2">{{ __('messages.first_name_label') }}</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required autofocus>
                    @error('first_name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="last_name" class="block text-gray-700 mb-2">{{ __('messages.last_name_label') }}</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('last_name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-gray-700 mb-2">{{ __('messages.username_label') }}</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('username')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-gray-700 mb-2">{{ __('messages.auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('email')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-gray-700 mb-2">{{ __('messages.auth.password') }}</label>
                    <input id="password" type="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password-confirm"
                           class="block text-gray-700 mb-2">{{ __('messages.auth.confirm_password') }}</label>
                    <input id="password-confirm" type="password" name="password_confirmation"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div class="mb-6">
                    <label for="profile_picture"
                           class="block text-gray-700 mb-2">{{ __('messages.auth.profile_picture_optional') }}</label>
                    <div class="relative border border-gray-300 rounded-md p-2">
                        <div class="flex items-center">
                            <div id="profile_picture_preview"
                                 class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-500">{{ __('messages.auth.upload_profile_picture_cta') }}</span>
                                    <button type="button" onclick="document.getElementById('profile_picture').click()"
                                            class="text-sm text-blue-800 hover:underline">
                                        {{ __('messages.choose_file_button') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input id="profile_picture" type="file" name="profile_picture"
                               class="hidden" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                               onchange="previewProfilePicture(this)">
                    </div>
                    @error('profile_picture')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <button type="submit"
                            class="w-full bg-blue-800 text-white py-3 rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.register') }}
                    </button>
                </div>
                <div class="mb-4">
                    <label for="terms" class="flex items-center">
                        <input type="checkbox" name="terms" id="terms"
                               class="rounded border-gray-300 text-blue-800 focus:ring-blue-500" required>
                        <span class="ml-2 text-gray-700">{{ __('messages.auth.i_agree_to') }} <a
                                href="{{ route('terms') }}"
                                class="text-blue-800 hover:underline">{{ __('messages.terms_of_use_nav') }}</a></span>
                    </label>
                    @error('terms')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </form>

            <div class="flex items-center justify-center mt-4 mb-6">
                <span class="border-t border-gray-300 flex-grow mr-3"></span>
                <span class="text-gray-500 text-sm">{{ __('messages.auth.or') }}</span>
                <span class="border-t border-gray-300 flex-grow ml-3"></span>
            </div>

            <div class="mb-2">
                <a href="{{ route('auth.google') }}"
                   class="w-full flex items-center justify-center bg-white border border-gray-300 text-gray-700 py-2 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            fill="#4285F4"/>
                        <path
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            fill="#34A853"/>
                        <path
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                            fill="#FBBC05"/>
                        <path
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                            fill="#EA4335"/>
                    </svg>
                    {{ __('messages.auth.signup_with_google') }}
                </a>
            </div>

            <div class="mb-2">
                <a href="{{ route('auth.x') }}"
                   class="w-full flex items-center justify-center bg-black text-white py-2 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    {{ __('messages.auth.signup_with_x') }}
                </a>
            </div>

            <div class="mb-2">
                <a href="{{ route('auth.telegram.redirect') }}"
                   class="w-full flex items-center justify-center bg-[#2AABEE] text-white py-2 rounded-md hover:bg-[#1E98D4] focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.57c-.28 1.13-1.04 1.4-1.74.88L14.25 16l-4.12 3.9c-.78.76-1.36.37-1.57-.49z"/>
                    </svg>
                    <span>{{ __('messages.auth.signup_with_telegram') }}</span>
                </a>
            </div>

            <p class="text-center text-gray-600 mt-4">
                {{ __('messages.auth.already_have_account') }}
                <a href="{{ route('login') }}"
                   class="text-blue-800 hover:underline">{{ __('messages.auth.login_here') }}</a>
            </p>
        </div>
    </div>

    <script>
        function previewProfilePicture(input) {
            const preview = document.getElementById('profile_picture_preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.classList.add('w-10', 'h-10', 'rounded-full', 'object-cover');
                    preview.appendChild(img);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        const usernameValidationMessages = {
            min_length: "{{ __('messages.username_min_length') }}",
            max_length: "{{ __('messages.username_max_length') }}",
            starts_with_letter: "{{ __('messages.username_startsWithLetter') }}",
            only_valid_chars: "{{ __('messages.username_onlyValidChars') }}",
            not_only_numbers: "{{ __('messages.username_notOnlyNumbers') }}",
            no_consecutive_chars: "{{ __('messages.username_noConsecutiveChars') }}",
            checking: "{{ __('messages.username_availability_checking') }}",
            available: "{{ __('messages.username_available') }}",
            taken: "{{ __('messages.username_taken') }}",
            could_not_verify: "{{ __('messages.username_could_not_verify') }}"
        };

        function initUsernameChecker() {
            const usernameInput = document.getElementById('username');
            if (!usernameInput) return;
            const debounceTimeout = 500;
            let typingTimer;
            let lastCheckedUsername = usernameInput.value.trim();

            const existingStatusElement = document.getElementById('username-status');
            if (existingStatusElement) {
                existingStatusElement.remove();
            }

            const statusElement = document.createElement('div');
            statusElement.id = 'username-status';
            statusElement.className = 'mt-1 text-sm';
            if (usernameInput.nextElementSibling && usernameInput.nextElementSibling.tagName === 'SPAN' && usernameInput.nextElementSibling.classList.contains('text-red-500')) {
                usernameInput.nextElementSibling.insertAdjacentElement('afterend', statusElement);
            } else {
                usernameInput.parentNode.insertBefore(statusElement, usernameInput.nextSibling);
            }


            function checkUsername() {
                const username = usernameInput.value.trim();

                if (username === '' || username === lastCheckedUsername && !statusElement.textContent.includes(usernameValidationMessages.taken) && !statusElement.textContent.includes(usernameValidationMessages.could_not_verify)) {
                    if (username === '') {
                        statusElement.textContent = '';
                        usernameInput.classList.remove('border-red-500', 'border-green-500');
                    }
                    if (username === lastCheckedUsername && usernameInput.classList.contains('border-green-500')) return;
                }


                const minLength = 5;
                const maxLength = 24;
                const startsWithLetter = /^[a-zA-Z]/.test(username);
                const onlyValidChars = /^[a-zA-Z0-9_-]+$/.test(username);
                const notOnlyNumbers = !/^\d+$/.test(username);
                const noConsecutiveChars = !/(.)\1{3,}/.test(username);

                let clientSideError = false;
                let errorMessageKey = null;

                if (username.length < minLength) {
                    errorMessageKey = 'min_length';
                    clientSideError = true;
                } else if (username.length > maxLength) {
                    errorMessageKey = 'max_length';
                    clientSideError = true;
                } else if (!startsWithLetter) {
                    errorMessageKey = 'starts_with_letter';
                    clientSideError = true;
                } else if (!onlyValidChars) {
                    errorMessageKey = 'only_valid_chars';
                    clientSideError = true;
                } else if (!notOnlyNumbers) {
                    errorMessageKey = 'not_only_numbers';
                    clientSideError = true;
                } else if (!noConsecutiveChars) {
                    errorMessageKey = 'no_consecutive_chars';
                    clientSideError = true;
                }


                if (clientSideError) {
                    statusElement.textContent = usernameValidationMessages[errorMessageKey] || 'Validation error.';
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                usernameInput.classList.remove('border-red-500');
                statusElement.className = 'mt-1 text-sm text-gray-500';
                statusElement.textContent = usernameValidationMessages.checking;

                fetch('/check-username?username=' + encodeURIComponent(username), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errData => {
                                throw {status: response.status, data: errData};
                            }).catch(() => {
                                throw {status: response.status, data: {message: 'Network response was not ok.'}};
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.available) {
                            statusElement.className = 'mt-1 text-sm text-green-600';
                            statusElement.textContent = data.message || usernameValidationMessages.available;
                            usernameInput.classList.remove('border-red-500');
                            usernameInput.classList.add('border-green-500');
                            lastCheckedUsername = username;
                        } else {
                            statusElement.className = 'mt-1 text-sm text-red-600';
                            statusElement.textContent = data.message || usernameValidationMessages.taken;
                            usernameInput.classList.remove('border-green-500');
                            usernameInput.classList.add('border-red-500');
                            lastCheckedUsername = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking username:', error);
                        statusElement.className = 'mt-1 text-sm text-red-600';
                        statusElement.textContent = usernameValidationMessages.could_not_verify;
                        usernameInput.classList.remove('border-green-500');
                        usernameInput.classList.add('border-red-500');
                        lastCheckedUsername = '';
                    });
            }

            usernameInput.addEventListener('input', function () {
                clearTimeout(typingTimer);
                if (!usernameInput.classList.contains('border-green-500')) {
                    usernameInput.classList.remove('border-red-500');
                    statusElement.textContent = '';
                }
                typingTimer = setTimeout(checkUsername, debounceTimeout);
            });

            usernameInput.addEventListener('blur', function () {
                if (usernameInput.value.trim() !== '' && (!usernameInput.classList.contains('border-green-500') || usernameInput.classList.contains('border-red-500'))) {
                    checkUsername();
                }
            });
            if (usernameInput.value.trim() !== '') {
                checkUsername();
            }
        }

        document.addEventListener('DOMContentLoaded', initUsernameChecker);
    </script>
@endsection
