@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-6 text-blue-800">Edit Profile</h2>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="first_name" class="block text-gray-700 mb-2">First Name</label>
                    <input id="first_name" type="text" name="first_name"
                           value="{{ old('first_name', $user->first_name) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('first_name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="last_name" class="block text-gray-700 mb-2">Last Name (Optional)</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('last_name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-gray-700 mb-2">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('username')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="profile_picture" class="block text-gray-700 mb-2">Update Profile Picture
                        (Optional)</label>
                    @php
                        $profilePic = $user->profile_picture
                            ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                                ? $user->profile_picture
                                : asset('storage/' . $user->profile_picture))
                            : asset('images/default-pfp.png');
                    @endphp

                    <div class="flex items-center mb-3">
                        <span class="mr-2 text-sm text-gray-600">Current:</span>
                        <div class="h-16 w-16 rounded-full overflow-hidden border border-gray-200">
                            <img src="{{ $profilePic }}" alt="Current Profile Picture"
                                 class="h-full w-full object-cover">
                        </div>
                    </div>

                    <div class="relative border border-gray-300 rounded-md p-2">
                        <div class="flex items-center">
                            <div id="profile_picture_preview"
                                 class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Upload a new profile picture</span>
                                    <button type="button" onclick="document.getElementById('profile_picture').click()"
                                            class="text-sm text-blue-800 hover:underline">
                                        Choose file
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

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Update Profile
                    </button>
                    <a href="{{ route('profile.show', $user->username) }}"
                       class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Cancel
                    </a>
                </div>
            </form>

            @if($user->password)
                <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between">
                    <a href="{{ route('password.change.form') }}" class="text-blue-800 hover:underline">
                        Change Your Password
                    </a>

                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-red-600 hover:underline cursor-pointer">
                            Logout
                        </button>
                    </form>

                </div>
            @elseif($user->google_id)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-gray-600 text-sm">
                        Password change is not available for accounts created via Google login unless a password has
                        been set.
                    </p>
                </div>
            @endif

        </div>
    </div>

    <script>
        function previewProfilePicture(input) {
            const preview = document.getElementById('profile_picture_preview');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function (e) {
                    // Create an image element and set its source
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.classList.add('w-10', 'h-10', 'rounded-full', 'object-cover');
                    preview.appendChild(img);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        function initUsernameChecker() {
            const usernameInput = document.getElementById('username');
            const debounceTimeout = 500;
            let typingTimer;
            let lastCheckedUsername = '';

            const statusElement = document.createElement('div');
            statusElement.id = 'username-status';
            statusElement.className = 'mt-1 text-sm';

            usernameInput.parentNode.insertBefore(statusElement, usernameInput.nextSibling);

            function checkUsername() {
                const username = usernameInput.value.trim();

                if (username === '' || username === lastCheckedUsername) {
                    return;
                }

                // Client-side validation
                const minLength = 5;
                const maxLength = 24;
                const startsWithLetter = /^[a-zA-Z]/.test(username);
                const onlyValidChars = /^[a-zA-Z0-9_-]+$/.test(username);
                const notOnlyNumbers = !/^\d+$/.test(username);
                const noConsecutiveChars = !/(.)\1{2,}/.test(username);

                if (username.length < minLength) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = 'Username must be at least 5 characters';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }
                if (username.length > maxLength) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = 'Username must be at most 24 characters';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                if (!startsWithLetter) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = 'Username must start with a letter';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                if (!onlyValidChars) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = 'Username can only contain letters, numbers, underscores, and hyphens';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                if (!notOnlyNumbers) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = 'Username cannot consist of only numbers';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                if (!noConsecutiveChars) {
                    statusElement.className = 'mt-1 text-sm text-red-600';
                    statusElement.textContent = 'Username cannot contain consecutive identical characters';
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }

                lastCheckedUsername = username;

                statusElement.className = 'mt-1 text-sm text-gray-500';
                statusElement.textContent = 'Checking availability...';

                // Proceed with the AJAX request only if client-side validation passes
                fetch('/check-username?username=' + encodeURIComponent(username), {
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
                            statusElement.textContent = 'Username is available';
                            usernameInput.classList.remove('border-red-500');
                            usernameInput.classList.add('border-green-500');
                        } else {
                            statusElement.className = 'mt-1 text-sm text-red-600';
                            statusElement.textContent = 'Username is already taken';
                            usernameInput.classList.remove('border-green-500');
                            usernameInput.classList.add('border-red-500');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking username:', error);
                        statusElement.className = 'mt-1 text-sm text-gray-500';
                        statusElement.textContent = 'Could not verify username';
                    });
            }

            usernameInput.addEventListener('input', function () {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(checkUsername, debounceTimeout);
            });

            usernameInput.addEventListener('blur', checkUsername);

            const currentPageUrl = window.location.pathname;
            if (currentPageUrl.includes('/profile/edit')) {
                lastCheckedUsername = usernameInput.value.trim();
            }
        }

        document.addEventListener('DOMContentLoaded', initUsernameChecker);
    </script>
@endsection
