@extends('layouts.app')
@section('title', __('Reset Password'))
@section('content')
    <div
        class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)] overflow-hidden my-10">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-4 text-blue-800 dark:text-blue-400">{{ __('Reset Password') }}</h2>
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-4">
                    <label for="email"
                           class="block text-gray-700 dark:text-gray-300 mb-2">{{ __('E-Mail Address') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-50 placeholder-gray-400 dark:placeholder-gray-500">
                    @error('email') <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="mb-4">
                    <button type="submit"
                            class="w-full bg-blue-800 dark:bg-blue-600 text-white py-3 rounded-md hover:bg-blue-900 dark:hover:bg-blue-700">
                        {{ __('Send Password Reset Link') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
