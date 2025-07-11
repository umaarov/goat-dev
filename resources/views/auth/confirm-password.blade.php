@extends('layouts.app')

@section('title', __('Confirm Password'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-2 text-blue-800">{{ __('Confirm Password') }}</h2>
            <p class="text-gray-600 mb-6">{{ __('For your security, please confirm your password to continue.') }}</p>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-4">
                    <label for="password" class="block text-gray-700 mb-2">{{ __('Password') }}</label>
                    <input id="password" type="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-500 @enderror"
                           required autocomplete="current-password">

                    @error('password')
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('Confirm Password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
