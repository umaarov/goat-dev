@extends('layouts.app')

@section('title', __('Set Your Password'))

@section('content')
    <div
        class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)] overflow-hidden my-10">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-2 text-blue-800 dark:text-blue-400">{{ __('Set Your Account Password') }}</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-6">{{ __('Because you signed up with Google, you need to create a password to access this feature.') }}</p>

            <form method="POST" action="{{ route('password.set') }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="password"
                           class="block text-gray-700 dark:text-gray-300 mb-2">{{ __('New Password') }}</label>
                    <input id="password" type="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-50 @error('password') border-red-500 dark:border-red-400 @enderror"
                           required>
                    @error('password')
                    <span class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password_confirmation"
                           class="block text-gray-700 dark:text-gray-300 mb-2">{{ __('Confirm New Password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-50"
                           required>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-800 dark:bg-blue-600 text-white rounded-md hover:bg-blue-900 dark:hover:bg-blue-700">
                        {{ __('Set Password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
