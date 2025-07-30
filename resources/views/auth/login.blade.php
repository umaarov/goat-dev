@extends('layouts.app')

@section('title', __('messages.login'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6 relative">
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
            <h2 class="text-2xl font-semibold mb-4 text-blue-800">{{ __('messages.login') }}</h2>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-4">
                    <label for="login_identifier"
                           class="block text-gray-700 mb-2">{{ __('messages.auth.email_or_username') }}</label>
                    <input id="login_identifier" type="text" name="login_identifier"
                           value="{{ old('login_identifier') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required autofocus>
                    @error('login_identifier')
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

                <div class="flex items-center justify-between mb-6">
                    {{-- Remember Me Checkbox --}}
                    <label for="remember" class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-800 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">{{ __('messages.auth.remember_me') }}</span>
                    </label>

                    {{-- Forgot Password Link --}}
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-800 hover:underline">
                        {{ __('messages.auth.forgot_password') }}
                    </a>
                </div>

                <div class="mb-4">
                    <button type="submit"
                            class="w-full bg-blue-800 text-white py-3 rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.login') }}
                    </button>
                </div>
                <div class="mb-4 text-sm text-gray-600">
                    {{ __('messages.auth.agree_to_terms_prefix') }} <a href="{{ route('terms') }}"
                                                                       class="text-blue-800 hover:underline">{{ __('messages.terms_of_use_nav') }}</a>.
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
                    {{ __('messages.auth.login_with_google') }}
                </a>
            </div>

            <div class="mb-2">
                <a href="{{ route('auth.x') }}"
                   class="w-full flex items-center justify-center bg-black text-white py-2 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    {{ __('messages.auth.login_with_x') }}
                </a>
            </div>

            <p class="text-center text-gray-600 mt-4">
                {{ __('messages.auth.dont_have_account') }}
                <a href="{{ route('register') }}"
                   class="text-blue-800 hover:underline">{{ __('messages.auth.register_here') }}</a>
            </p>
        </div>
    </div>
@endsection
