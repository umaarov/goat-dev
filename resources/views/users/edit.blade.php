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
                    <label class="block text-gray-700 mb-2">Update Profile Picture (Optional)</label>
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
                                 class="h-full w-full object-cover" id="current_profile_picture_img">
                        </div>
                    </div>

                    <div class="relative border border-gray-300 rounded-md p-2">
                        <div class="flex items-center">
                            <div id="profile_picture_preview"
                                 class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3 overflow-hidden">
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
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror

                    <div class="mt-3 mb-2">
                        <label for="remove_profile_picture"
                               class="flex items-center text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" id="remove_profile_picture" name="remove_profile_picture" value="1"
                                   class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span>Revert to initials-based profile picture</span>
                        </label>
                    </div>
                </div>

                <div class="mb-6 pt-4 border-t border-gray-200">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Privacy Settings</label>
                    <div class="flex items-center">
                        <input type="hidden" name="show_voted_posts_publicly"
                               value="0">
                        <input type="checkbox" id="show_voted_posts_publicly" name="show_voted_posts_publicly" value="1"
                               class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                               @if(old('show_voted_posts_publicly', $user->show_voted_posts_publicly ?? true)) checked @endif> {{-- Default to checked (public) if not set --}}
                        <label for="show_voted_posts_publicly" class="ml-2 text-sm text-gray-700">Show "Voted Posts" tab
                            publicly on my profile</label>
                    </div>
                    @error('show_voted_posts_publicly')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">If unchecked, this tab will only be visible to you.</p>
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
        const defaultPreviewIconSVG = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4v16m8-8H4"/>
            </svg>`;

        function previewProfilePicture(input) {
            const preview = document.getElementById('profile_picture_preview');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = "New profile picture preview";
                    img.classList.add('w-10', 'h-10', 'rounded-full', 'object-cover');
                    preview.appendChild(img);
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = defaultPreviewIconSVG;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            initUsernameChecker();

            const profilePictureInput = document.getElementById('profile_picture');
            const removeProfilePictureCheckbox = document.getElementById('remove_profile_picture');

            if (profilePictureInput && removeProfilePictureCheckbox) {
                profilePictureInput.addEventListener('change', function () {
                    if (this.files && this.files.length > 0) {
                        removeProfilePictureCheckbox.checked = false;
                    }
                });

                removeProfilePictureCheckbox.addEventListener('change', function () {
                    if (this.checked) {
                        profilePictureInput.value = '';

                        const changeEvent = new Event('change', {bubbles: true});
                        profilePictureInput.dispatchEvent(changeEvent);
                    }
                });
            }
        });

        function initUsernameChecker() {
            const usernameInput = document.getElementById('username');
            const debounceTimeout = 500;
            let typingTimer;
            let lastCheckedUsername = '';

            const statusElement = document.createElement('div');
            statusElement.id = 'username-status';
            statusElement.className = 'mt-1 text-sm';

            if (!document.getElementById('username-status')) {
                usernameInput.parentNode.insertBefore(statusElement, usernameInput.nextSibling);
            }


            function checkUsername() {
                const username = usernameInput.value.trim();
                const currentStatusElement = document.getElementById('username-status'); // Re-fetch in case of DOM changes

                if (username === '' || username === lastCheckedUsername) {
                    if (username === '' && currentStatusElement) {
                        currentStatusElement.textContent = ''; // Clear message if username is empty
                        usernameInput.classList.remove('border-red-500', 'border-green-500');
                    }
                    return;
                }


                const minLength = 5;
                const maxLength = 24;
                const startsWithLetter = /^[a-zA-Z]/.test(username);
                const onlyValidChars = /^[a-zA-Z0-9_-]+$/.test(username);
                const notOnlyNumbers = !/^\d+$/.test(username);
                const noConsecutiveChars = !/(.)\1{2,}/.test(username);

                let errorMessage = null;

                if (username.length < minLength) {
                    errorMessage = 'Username must be at least 5 characters';
                } else if (username.length > maxLength) {
                    errorMessage = 'Username must be at most 24 characters';
                } else if (!startsWithLetter) {
                    errorMessage = 'Username must start with a letter';
                } else if (!onlyValidChars) {
                    errorMessage = 'Username can only contain letters, numbers, underscores, and hyphens';
                } else if (!notOnlyNumbers) {
                    errorMessage = 'Username cannot consist of only numbers';
                } else if (!noConsecutiveChars) {
                    errorMessage = 'Username cannot contain more than 2 consecutive identical characters';
                }

                if (errorMessage) {
                    if (currentStatusElement) {
                        currentStatusElement.className = 'mt-1 text-sm text-red-600';
                        currentStatusElement.textContent = errorMessage;
                    }
                    usernameInput.classList.remove('border-green-500');
                    usernameInput.classList.add('border-red-500');
                    return;
                }


                lastCheckedUsername = username;

                if (currentStatusElement) {
                    currentStatusElement.className = 'mt-1 text-sm text-gray-500';
                    currentStatusElement.textContent = 'Checking availability...';
                }


                fetch('/check-username?username=' + encodeURIComponent(username), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                })
                    .then(response => response.json())
                    .then(data => {
                        if (currentStatusElement) {
                            if (data.available) {
                                currentStatusElement.className = 'mt-1 text-sm text-green-600';
                                currentStatusElement.textContent = 'Username is available';
                                usernameInput.classList.remove('border-red-500');
                                usernameInput.classList.add('border-green-500');
                            } else {
                                currentStatusElement.className = 'mt-1 text-sm text-red-600';
                                currentStatusElement.textContent = 'Username is already taken';
                                usernameInput.classList.remove('border-green-500');
                                usernameInput.classList.add('border-red-500');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error checking username:', error);
                        if (currentStatusElement) {
                            currentStatusElement.className = 'mt-1 text-sm text-gray-500';
                            currentStatusElement.textContent = 'Could not verify username';
                        }
                        usernameInput.classList.remove('border-green-500', 'border-red-500');
                    });
            }

            usernameInput.addEventListener('input', function () {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(checkUsername, debounceTimeout);
            });

            usernameInput.addEventListener('blur', checkUsername);

            const currentPageUrl = window.location.pathname;
            if (currentPageUrl.includes('/profile/edit')) {
                if (usernameInput.value && !usernameInput.classList.contains('border-red-500') && !document.querySelector('.text-red-500.text-sm')) {
                    lastCheckedUsername = usernameInput.value.trim();
                }
            }
        }
    </script>
@endsection
