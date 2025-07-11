@extends('layouts.app')

@section('title', __('Set Your Password'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden my-10">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-2 text-blue-800">{{ __('Set Your Account Password') }}</h2>
            <p class="text-gray-600 mb-6">{{ __('Because you signed up with Google, you need to create a password to access this feature.') }}</p>

            <form method="POST" action="{{ route('password.set') }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="password" class="block text-gray-700 mb-2">{{ __('New Password') }}</label>
                    <input id="password" type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-500 @enderror" required>
                    @error('password')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password_confirmation" class="block text-gray-700 mb-2">{{ __('Confirm New Password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button type="submit" class="px-6 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900">
                        {{ __('Set Password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
