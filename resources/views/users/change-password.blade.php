@extends('layouts.app')

@section('title', __('messages.change_password.page_title'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-2xl font-semibold mb-6 text-blue-800">{{ __('messages.change_password.heading') }}</h2>

            <form method="POST" action="{{ route('password.change') }}">
                @csrf

                <div class="mb-4">
                    <label for="current_password"
                           class="block text-gray-700 mb-2">{{ __('messages.change_password.current_password_label') }}</label>
                    <input id="current_password" type="password" name="current_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('current_password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="new_password"
                           class="block text-gray-700 mb-2">{{ __('messages.change_password.new_password_label') }}</label>
                    <input id="new_password" type="password" name="new_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('new_password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="new_password_confirmation"
                           class="block text-gray-700 mb-2">{{ __('messages.change_password.confirm_new_password_label') }}</label>
                    <input id="new_password_confirmation" type="password" name="new_password_confirmation"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="px-6 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.change_password.submit_button') }}
                    </button>
                    <a href="{{ route('profile.edit') }}"
                       class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.cancel_button') }} {{-- Assuming 'cancel_button' key exists --}}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
