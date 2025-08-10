@extends('layouts.app')
{{--@section('meta_robots', 'noindex, follow')--}}
@section('title', __('messages.login'))

@section('content')
    <div
        class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)] overflow-hidden mb-4">
        <div class="p-6 relative">
            @if(isset($available_locales) && is_array($available_locales) && count($available_locales) > 1)
                <div class="absolute top-6 right-6 z-10">
                    <div class="relative">
                        <select onchange="window.location.href=this.value;"
                                aria-label="{{ __('messages.select_language_label') ?? 'Select Language' }}"
                                class="block appearance-none w-auto bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:border-gray-400 px-3 py-1.5 pr-7 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-xs text-gray-700 dark:text-gray-300">
                            @foreach($available_locales as $localeKey => $localeName)
                                <option
                                    value="{{ route('language.set', $localeKey) }}" {{ ($current_locale ?? app()->getLocale()) == $localeKey ? 'selected' : '' }}>
                                    {{ $localeName }}
                                </option>
                            @endforeach
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 text-gray-700 dark:text-gray-400">
                            <svg class="fill-current h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            @endif
            <h2 class="text-2xl font-semibold mb-4 text-blue-800 dark:text-blue-400">{{ __('messages.login') }}</h2>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-4">
                    <label for="login_identifier"
                           class="block text-gray-700 dark:text-gray-300 mb-2">{{ __('messages.auth.email_or_username') }}</label>
                    <input id="login_identifier" type="text" name="login_identifier"
                           value="{{ old('login_identifier') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-50 placeholder-gray-400 dark:placeholder-gray-500"
                           required autofocus>
                    @error('login_identifier')
                    <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password"
                           class="block text-gray-700 dark:text-gray-300 mb-2">{{ __('messages.auth.password') }}</label>
                    <input id="password" type="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-50 placeholder-gray-400 dark:placeholder-gray-500"
                           required>
                    @error('password')
                    <span class="text-red-500 dark:text-red-400 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-between mb-6">
                    <label for="remember" class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}
                        class="rounded border-gray-300 dark:border-gray-500 dark:bg-gray-600 text-blue-800 dark:text-blue-500 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                        <span
                            class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('messages.auth.remember_me') }}</span>
                    </label>

                    <a href="{{ route('password.request') }}"
                       class="text-sm text-blue-800 dark:text-blue-400 hover:underline">
                        {{ __('messages.auth.forgot_password') }}
                    </a>
                </div>

                <div class="mb-4">
                    <button type="submit"
                            class="w-full bg-blue-800 dark:bg-blue-600 text-white py-3 rounded-md hover:bg-blue-900 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ __('messages.login') }}
                    </button>
                </div>
                <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('messages.auth.agree_to_terms_prefix') }} <a href="{{ route('terms') }}"
                                                                       class="text-blue-800 dark:text-blue-400 hover:underline">{{ __('messages.terms_of_use_nav') }}</a>.
                </div>
            </form>

            <div class="flex items-center justify-center mt-4 mb-6">
                <span class="border-t border-gray-300 dark:border-gray-600 flex-grow mr-3"></span>
                <span class="text-gray-500 dark:text-gray-400 text-sm">{{ __('messages.auth.or') }}</span>
                <span class="border-t border-gray-300 dark:border-gray-600 flex-grow ml-3"></span>
            </div>

            {{-- Social Auth Buttons --}}
            <div class="space-y-3">
                <a href="{{ route('auth.google') }}"
                   class="w-full flex items-center justify-center bg-white dark:bg-gray-200 border border-gray-300 dark:border-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
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
                    <span class="font-medium text-sm text-gray-800">{{ __('messages.auth.login_with_google') }}</span>
                </a>
                <a href="{{ route('auth.x') }}"
                   class="w-full flex items-center justify-center bg-black text-white py-2.5 rounded-lg hover:bg-gray-800 dark:hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black transition-colors">
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    <span class="font-medium text-sm">{{ __('messages.auth.login_with_x') }}</span>
                </a>
                <a href="{{ route('auth.telegram.redirect') }}"
                   class="w-full flex items-center justify-center bg-[#2AABEE] text-white py-2.5 rounded-lg hover:bg-[#1E98D4] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.57c-.28 1.13-1.04 1.4-1.74.88L14.25 16l-4.12 3.9c-.78.76-1.36.37-1.57-.49z"/>
                    </svg>
                    <span class="font-medium text-sm">{{ __('messages.auth.login_with_telegram') }}</span>
                </a>
            </div>

            <div
                class="mt-6 p-4 text-sm bg-indigo-50 dark:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-800 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-6 h-6 mr-3 flex-shrink-0 text-indigo-500 dark:text-indigo-400" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 15"></path>
                    </svg>
                    <div>
                        <h4 class="font-bold text-indigo-800 dark:text-indigo-300">{{ __('messages.auth.github_developer_perks_title') }}</h4>
                        <p class="mt-1 text-indigo-700 dark:text-indigo-400">{{ __('messages.auth.github_developer_perks_desc') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="button" id="github-auth-btn"
                        class="w-full flex items-center justify-center bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 py-2.5 rounded-lg hover:bg-gray-900 dark:hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-700 dark:focus:ring-gray-400 transition-colors">
                    <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd"
                              d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.168 6.839 9.492.5.092.682-.217.682-.482 0-.237-.009-.868-.014-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.031-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.03 1.595 1.03 2.688 0 3.848-2.338 4.695-4.566 4.942.359.308.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.003 10.003 0 0022 12c0-5.523-4.477-10-10-10z"
                              clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium text-sm">{{ __('messages.auth.login_with_github') }}</span>
                </button>
            </div>

            <p class="text-center text-gray-600 dark:text-gray-400 mt-4">
                {{ __('messages.auth.dont_have_account') }}
                <a href="{{ route('register') }}"
                   class="text-blue-800 dark:text-blue-400 hover:underline">{{ __('messages.auth.register_here') }}</a>
            </p>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const githubBtn = document.getElementById('github-auth-btn');

            if (githubBtn) {
                githubBtn.addEventListener('click', function (event) {
                    event.preventDefault();

                    if (typeof window.showToast === 'function') {
                        const message = "{{ __('messages.auth.feature_coming_soon') ?? 'This feature is coming soon!' }}";
                        window.showToast(message, 'info');
                    } else {
                        alert("{{ __('messages.auth.feature_coming_soon') ?? 'This feature is coming soon!' }}");
                    }
                });
            }
        });
    </script>
@endsection
