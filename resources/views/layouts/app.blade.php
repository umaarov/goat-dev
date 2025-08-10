<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <title>@yield('title', __('messages.app.default_title', ['default_app_name' => config('app.name', 'GOAT')]))</title>
    <link rel="icon" href="{{ asset('images/favicon-96x96.png') }}" type="image/png" sizes="96x96">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="shortcut" href="{{ asset('images/favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-title" content="GOAT">
    <link rel="manifest" href="{{ asset('images/site.webmanifest') }}">
    <meta name="msapplication-TileColor" content="#1f2937">
    <meta name="msapplication-config" content="/browserconfig.xml">
    <link rel="search" type="application/opensearchdescription+xml" title="GOAT.uz"
          href="{{ asset('opensearch.xml') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('styles')
    <script>
        (function () {
            window.themeManager = {
                key: 'theme',

                applyTheme(preference) {
                    const resolvedTheme = preference === 'system'
                        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                        : preference;

                    if (resolvedTheme === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }

                    const event = new CustomEvent('theme:changed', {
                        detail: {themePreference: preference, resolvedTheme}
                    });
                    document.dispatchEvent(event);
                },

                set(newPreference) {
                    if (!['light', 'dark', 'system'].includes(newPreference)) {
                        newPreference = 'system';
                    }
                    localStorage.setItem(this.key, newPreference);
                    this.applyTheme(newPreference);
                },

                get() {
                    return localStorage.getItem(this.key) || 'system';
                },

                init() {
                    this.applyTheme(this.get());

                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (this.get() === 'system') {
                            this.applyTheme('system');
                        }
                    });
                }
            };
            window.themeManager.init();
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{--    @vite(['resources/css/posts.css'])--}}
    {{--    <link rel="stylesheet" href="{{ Illuminate\Support\Facades\Vite::asset('resources/css/app.css') }}" media="print"--}}
    {{--          onload="this.media='all'">--}}
    {{--    <noscript>--}}
    {{--        <link rel="stylesheet" href="{{ Illuminate\Support\Facades\Vite::asset('resources/css/app.css') }}">--}}
    {{--    </noscript>--}}
    {{--    @vite(['resources/js/app.js'])--}}
    {{--    @include('partials.critical-css')--}}
    {{--    <link rel="stylesheet" href="{{ Illuminate\Support\Facades\Vite::asset('resources/css/app.css') }}" media="print" onload="this.media='all'">--}}
    {{--    <noscript><link rel="stylesheet" href="{{ Illuminate\Support\Facades\Vite::asset('resources/css/app.css') }}"></noscript>--}}

    {{--    <script src="https://cmp.gatekeeperconsent.com/min.js" data-cfasync="false"></script>--}}
    {{--    <script src="https://the.gatekeeperconsent.com/cmp.min.js" data-cfasync="false"></script>--}}
    {{--    <script async src="//www.ezojs.com/ezoic/sa.min.js"></script>--}}
    {{--    <script>--}}
    {{--        window.ezstandalone = window.ezstandalone || {};--}}
    {{--        ezstandalone.cmd = ezstandalone.cmd || [];--}}
    {{--    </script>--}}

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
          media="print" onload="this.media='all'">
    {{--    <script src="https://code.jquery.com/jquery-3.6.0.min.js"--}}
    {{--            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="--}}
    {{--            crossorigin="anonymous" defer></script>--}}
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"
            integrity="sha256-tgDjY9mdlURNtUrL+y3v/smueSqpmgkim82geOW1VkM="
            crossorigin="anonymous" defer></script>
    {{-- Cropper.js --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"
          integrity="sha512-hvNR0F/e2J7zPPfLC9auFe3/SE0yG4aJCOd/qxew74NN7eyiSKjr7xJJMu1Jy2wf7FXITpWS1E/RY8yzuXN7VA=="
          crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"
            integrity="sha512-9KkIqdfN7ipEW6B6k+Aq20PV31bjODg4AA52W+tYtAE0jE0kMx49bjJ3FgvS56wzmyfMUHbQ4Km2b7l9+Y/+Eg=="
            crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
    <meta name="description" content="@yield('meta_description', __('messages.app.meta_description_default'))">
    <link rel="canonical" href="@yield('canonical_url', url()->current())"/>

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta property="og:title" content="@yield('title', config('app.name', 'GOAT'))">
    <meta property="og:description" content="@yield('meta_description', __('messages.app.meta_description_default'))">
    <meta property="og:image" content="@yield('og_image', asset('images/goat.jpg'))">
    <meta property="og:site_name" content="GOAT.uz">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="@yield('canonical_url', url()->current())">
    <meta name="twitter:title" content="@yield('title', config('app.name', 'GOAT'))">
    <meta name="twitter:description" content="@yield('meta_description', __('messages.app.meta_description_default'))">
    <meta name="twitter:image" content="@yield('og_image', asset('images/goat.jpg'))">

    <meta name="robots" content="@yield('meta_robots', 'index, follow')">
    @if(isset($alternateUrls))
        @foreach($alternateUrls as $locale => $url)
            <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}"/>
        @endforeach
    @endif
    @if(isset($defaultHreflangUrl))
        <link rel="alternate" hreflang="x-default" href="{{ $defaultHreflangUrl }}"/>
    @endif
    {{--    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2989575196315667"--}}
    {{--            crossorigin="anonymous"></script>--}}
    {{--    <script src="https://cmp.gatekeeperconsent.com/min.js" data-cfasync="false"></script>--}}
    {{--    <script src="https://the.gatekeeperconsent.com/cmp.min.js" data-cfasync="false"></script>--}}
    {{--    <script async src="//www.ezojs.com/ezoic/sa.min.js"></script>--}}

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#1f2937">
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "@@id": "https://goat.uz#website",
            "name": "GOAT.uz",
            "url": "https://goat.uz",
            "alternateName": "GOAT",
            "potentialAction": {
                "@@type": "SearchAction",
                "target": {
                    "@@type": "EntryPoint",
                    "urlTemplate": "https://goat.uz/search?q={search_term_string}"
                },
                "query-input": "required name=search_term_string"
            },
            "publisher": {
                "@@type": "Organization",
                "name": "GOAT.uz",
                "url": "https://goat.uz",
                "logo": {
                    "@@type": "ImageObject",
                    "url": "https://goat.uz/images/icons/icon-512x512.png"
                }
            },
            "logo": "https://goat.uz/images/icons/icon-512x512.png",
            "founder": {
                "@@type": "Person",
                "name": "Ismoiljon Umarov"
            },
            "contactPoint": {
                "@@type": "ContactPoint",
                "contactType": "customer support",
                "email": "info@goat.uz",
                "telephone": "+998-33-500-25-17"
            }
        }
    </script>

    @stack('schema')
    <script>
        window.translations = {
            cropperModalTitle: "{{ __('messages.app.js.cropper_modal_title') }}",
            cancelButton: "{{ __('messages.cancel_button') }}",
            applyCropButton: "{{ __('messages.app.js.apply_crop_button') }}",
            errorProcessingCrop: "{{ __('messages.app.js.cropper_error_processing') }}",
            errorInitTool: "{{ __('messages.app.js.cropper_error_init') }}",
            tooltipVoteSingular: "{{ __('messages.app.js.tooltip_vote_singular') }}",
            tooltipVotePlural: "{{ __('messages.app.js.tooltip_vote_plural') }}",
            tooltipOwnerVotedForTemplate: "{{ __('messages.app.js.tooltip_owner_voted_for_template') }}",
            imageViewerAltText: "{{ __('messages.app.js.image_viewer_alt') }}",
            imageViewerCloseTitle: "{{ __('messages.app.js.image_viewer_close_title') }}",

            profile_alt_picture: "{{ __('messages.profile.alt_profile_picture_js', ['username' => ':username']) }}",
            verified_account: "{{ __('messages.profile.verified_account') }}",
            delete_comment_title: "{{ __('messages.profile.js.delete_comment_title') }}",
            time_just_now: "{{ __('messages.profile.js.time.just_now') }}",
            time_minute: "{{ __('messages.profile.js.time.minute') }}",
            time_minutes: "{{ __('messages.profile.js.time.minutes') }}",
            time_minutes_alt: "{{ __('messages.profile.js.time.minutes_alt') }}",
            time_hour: "{{ __('messages.profile.js.time.hour') }}",
            time_hours: "{{ __('messages.profile.js.time.hours') }}",
            time_hours_alt: "{{ __('messages.profile.js.time.hours_alt') }}",
            time_day: "{{ __('messages.profile.js.time.day') }}",
            time_days: "{{ __('messages.profile.js.time.days') }}",
            time_days_alt: "{{ __('messages.profile.js.time.days_alt') }}",
            time_ago: "{{ __('messages.profile.js.time.ago') }}",
            js_votes_label: "{{ __('messages.post_card.votes_label') }}",
            js_link_copied: "{{ __('messages.profile.js.link_copied') }}",
            js_login_to_comment: `{!! __('messages.post_card.js.login_to_comment', ['login_link' => route('login')]) !!}`,
            js_no_comments_be_first: "{{ __('messages.post_card.js.no_comments_be_first') }}",
            js_failed_load_comments: "{{ __('messages.profile.js.failed_load_comments') }}",
            js_comment_empty: "{{ __('messages.profile.js.comment_empty') }}",
            js_submit_comment_button: "{{ __('messages.submit_comment_button') }}",
            js_comment_button_submitting: "{{ __('messages.profile.js.comment_button_submitting') }}",
            js_error_prefix: "{{ __('messages.profile.js.error_prefix') }}",
            js_failed_add_comment: "{{ __('messages.post_card.js.failed_add_comment') }}",
            js_confirm_delete_comment_text: "{{ __('messages.confirm_delete_comment_text') }}",
            js_failed_delete_comment: "{{ __('messages.post_card.js.failed_delete_comment') }}",
            js_login_to_vote: "{{ __('messages.profile.js.login_to_vote') }}",
            js_vote_failed_connection: "{{ __('messages.profile.js.vote_failed_connection') }}",
            js_option_1_default_title: "{{ __('messages.post_card.js.option_1_default_title') }}",
            js_option_2_default_title: "{{ __('messages.post_card.js.option_2_default_title') }}",
            js_error_already_voted: "{{ __('messages.error_already_voted') }}",
            js_vote_registered_successfully: "{{ __('messages.vote_registered_successfully') }}",
        };
    </script>
</head>
<body class="flex flex-col min-h-screen bg-gray-100 dark:bg-gray-900">
<nav
    class="fixed top-0 left-0 right-0 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-b-xl shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)] z-50 h-16 flex items-center px-4 max-w-[450px] mx-auto">
    <div class="w-full max-w-md mx-auto flex items-center justify-between">
        <div class="w-6"></div>
        <a href="{{route('home')}}">
            <img src="{{ asset('images/main_logo.png') }}" alt="{{ __('messages.app.logo_alt') }}"
                 class="h-23 w-23 cursor-pointer dark:invert" width="92" height="92">
        </a>
        <div>
            @auth
                <a href="{{ route('notifications.index') }}" title="Notifications"
                   class="relative inline-block text-black dark:text-gray-200">

                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>

                    <span id="notification-badge"
                          class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white
                         {{ $unreadNotificationsCount > 0 ? 'active-pulse' : 'hidden' }}">
                {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
            </span>

                </a>
            @else
                <a href="{{ route('login') }}" title="Login Button" class="text-black dark:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </a>
            @endauth
        </div>
    </div>
</nav>

<main class="flex-grow pt-20 mx-auto w-full max-w-[450px] px-4 pb-16">
    {{--    @if (session('success'))--}}
    {{--        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-4">--}}
    {{--            {{ session('success') }}--}}
    {{--        </div>--}}
    {{--    @endif--}}

    {{--    @if (session('error'))--}}
    {{--        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">--}}
    {{--            {{ session('error') }}--}}
    {{--        </div>--}}
    {{--    @endif--}}

    {{--    @if (session('info'))--}}
    {{--        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-md mb-4">--}}
    {{--            {{ session('info') }}--}}
    {{--        </div>--}}
    {{--    @endif--}}

    {{--    @if ($errors->any())--}}
    {{--        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">--}}
    {{--            <ul class="list-disc pl-5">--}}
    {{--                @foreach ($errors->all() as $error)--}}
    {{--                    <li>{{ $error }}</li>--}}
    {{--                @endforeach--}}
    {{--            </ul>--}}
    {{--        </div>--}}
    {{--    @endif--}}

    @yield('content')
    <footer class="mb-8 text-center text-gray-700 dark:text-gray-400 text-xs leading-relaxed px-4">
        <div class="space-y-4">
            <div class="flex flex-wrap justify-center gap-4 text-sm text-blue-800 dark:text-blue-400">
                <a href="{{ route('about') }}" class="hover:underline">{{ __('messages.about_us_nav') }}</a>
                <a href="{{ route('privacy') }}"
                   class="hover:underline">{{ __('messages.privacy_policy.title_nav') }}</a>
                <a href="{{ route('terms') }}" class="hover:underline">{{ __('messages.terms_of_use_nav') }}</a>
                <a href="{{ route('sponsorship') }}" class="hover:underline">{{ __('messages.sponsorship_nav') }}</a>
                <a href="{{ route('ads') }}" class="hover:underline">{{ __('messages.ads_nav') }}</a>
                <a href="{{ route('contribution') }}"
                   class="hover:underline">{{ __('messages.contribution.title_nav') }}</a>
            </div>

            <div class="flex items-center justify-center gap-x-2 mb-1">
                <p class="font-semibold">{{ __('messages.copyright_text') }}</p>
                <a href="https://buymeacoffee.com/umarov" target="_blank" rel="noopener noreferrer"
                   title="Support this project with a coffee"
                   class="inline-block transition-transform duration-200 hover:scale-105">
                    <img src="{{ asset('images/bmc-logo-no-background.png') }}" alt="Buy Me A Coffee"
                         class="h-4 w-auto dark:invert"
                         loading="lazy">
                </a>
            </div>
        </div>
    </footer>

</main>


<nav
    class="fixed bottom-0 left-0 right-0 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)] rounded-t-xl z-10 h-20 max-w-[450px] mx-auto">
    <div class="w-full max-w-md mx-auto flex items-center justify-around h-full">
        <a href="{{ route('home') }}" title="{{ __('messages.home') }}"
           class="flex flex-col items-center justify-center text-gray-700  hover:text-blue-800 dark:text-gray-300 dark:hover:text-blue-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span class="text-xs mt-1">{{ __('messages.home') }}</span>
        </a>
        <a href="{{ route('search') }}" title="{{ __('messages.search') }}"
           class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800 dark:text-gray-300 dark:hover:text-blue-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <span class="text-xs mt-1">{{ __('messages.search') }}</span>
        </a>
        <a href="{{ route('posts.create') }}" title="{{ __('messages.post') }}"
           class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800 dark:text-gray-300 dark:hover:text-blue-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="text-xs mt-1">{{ __('messages.post') }}</span>
        </a>
        @auth
            <a href="{{ route('profile.show', ['username' => Auth::user()->username]) }}"
               title="{{ __('messages.account') }}"
               class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800 dark:text-gray-300 dark:hover:text-blue-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="text-xs mt-1">{{ __('messages.account') }}</span>
            </a>
        @else
            <a href="{{ route('login') }}" title="{{ __('messages.account') }}"
               class="flex flex-col items-center justify-center text-gray-700 hover:text-blue-800 dark:text-gray-300 dark:hover:text-blue-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="text-xs mt-1">{{ __('messages.account') }}</span>
            </a>
        @endauth
    </div>
</nav>
<x-toast/>

{{-- Image Cropper Modal and Utility Script --}}
<script>
    let currentCropperInstance = null;
    let currentFileInputTarget = null;
    let currentPreviewImgElement = null;
    let currentPlaceholderElement = null;
    let currentPreviewDivElement = null;
    let currentOriginalFile = null;
    window.lastTriggeredImageInputId = null;

    function initCropperModal() {
        if (document.getElementById('imageCropModalGlobal')) {
            return;
        }

        const modalHTML = `
        <div id="imageCropModalGlobal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl w-11/12 max-w-lg">
                <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">${window.translations.cropperModalTitle}</h3>
                <div class="mb-4" style="max-height: 60vh; overflow: hidden;">
                    <img id="imageToCropGlobal" src="#" alt="Image to crop" style="max-width: 100%;" loading="lazy">
                </div>
                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                    <button type="button" id="cancelCropGlobal" class="w-full sm:w-auto px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">${window.translations.cancelButton}</button>
                    <button type="button" id="applyCropGlobal" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">${window.translations.applyCropButton}</button>
                </div>
            </div>
        </div>
    `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        const modal = document.getElementById('imageCropModalGlobal');
        const applyCropBtn = document.getElementById('applyCropGlobal');
        const cancelCropBtn = document.getElementById('cancelCropGlobal');

        cancelCropBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            if (currentCropperInstance) {
                currentCropperInstance.destroy();
                currentCropperInstance = null;
            }
            if (window.lastTriggeredImageInputId) {
                const triggerInput = document.getElementById(window.lastTriggeredImageInputId);
                if (triggerInput) triggerInput.value = '';
            }
        });

        applyCropBtn.addEventListener('click', () => {
            if (currentCropperInstance && currentOriginalFile) {
                const canvas = currentCropperInstance.getCroppedCanvas({
                    imageSmoothingQuality: 'medium',
                });
                canvas.toBlob((blob) => {
                    if (blob) {
                        const croppedFile = new File([blob], currentOriginalFile.name, {
                            type: currentOriginalFile.type,
                            lastModified: Date.now()
                        });

                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(croppedFile);

                        if (currentFileInputTarget === 'profile_picture_final') {
                            const removePfpCheckbox = document.getElementById('remove_profile_picture');
                            if (removePfpCheckbox && removePfpCheckbox.checked) {
                                removePfpCheckbox.checked = false;
                            }
                        }

                        const targetInputElement = document.getElementById(currentFileInputTarget);
                        if (targetInputElement) {
                            targetInputElement.files = dataTransfer.files;
                            if (currentPreviewImgElement) {
                                currentPreviewImgElement.src = URL.createObjectURL(croppedFile);
                                currentPreviewImgElement.classList.remove('hidden');
                            }
                            if (currentPlaceholderElement) {
                                currentPlaceholderElement.classList.add('hidden');
                            }
                            if (currentPreviewDivElement) {
                                currentPreviewDivElement.classList.remove('border-dashed', 'border-red-500', 'hover:border-blue-500');
                                currentPreviewDivElement.classList.add('border-solid', 'border-gray-300');
                                const parentDiv = currentPreviewDivElement.closest('div');
                                if (parentDiv) {
                                    const errorSpan = parentDiv.querySelector('.text-red-500.text-sm.mt-1');
                                    if (errorSpan) errorSpan.style.display = 'none';
                                }
                            }
                        }
                    } else {
                        console.error('Could not create blob from canvas.');
                        if (typeof window.showToast === 'function') {
                            window.showToast(window.translations.errorProcessingCrop, 'error');
                        } else {
                            alert(window.translations.errorProcessingCrop);
                        }
                    }
                }, currentOriginalFile.type);

                modal.classList.add('hidden');
                if (currentCropperInstance) {
                    currentCropperInstance.destroy();
                    currentCropperInstance = null;
                }
            }
        });
    }

    function openImageCropper(event, targetInputId, previewImgId, placeholderId, previewDivId) {
        const file = event.target.files[0];
        if (!file) return;

        window.lastTriggeredImageInputId = event.target.id;
        currentOriginalFile = file;
        currentFileInputTarget = targetInputId;
        currentPreviewImgElement = document.getElementById(previewImgId);
        currentPlaceholderElement = document.getElementById(placeholderId);
        currentPreviewDivElement = document.getElementById(previewDivId);

        const modal = document.getElementById('imageCropModalGlobal');
        const imageToCropElement = document.getElementById('imageToCropGlobal');

        if (!modal || !imageToCropElement) {
            console.error('Cropper modal elements not found. Was initCropperModal called?');
            if (typeof window.showToast === 'function') {
                window.showToast(window.translations.errorInitTool, 'error');
            } else {
                alert(window.translations.errorInitTool);
            }
            return;
        }


        const reader = new FileReader();
        reader.onload = (e) => {
            imageToCropElement.src = e.target.result;
            modal.classList.remove('hidden');
            if (currentCropperInstance) {
                currentCropperInstance.destroy();
            }
            currentCropperInstance = new Cropper(imageToCropElement, {
                aspectRatio: 1,
                viewMode: 1,
                background: false,
                autoCropArea: 0.85,
                responsive: true,
                checkCrossOrigin: false,
                // guides: false,
                // center: false,
                // cropBoxResizable: false,
                // dragMode: 'move',
            });
        };
        reader.readAsDataURL(file);
    }


    document.addEventListener('DOMContentLoaded', initCropperModal);
</script>

@stack('scripts')
<script src="{{ asset('js/toast.js') }}"></script>
<div id="voteCountTooltip"
     class="fixed hidden bg-gray-700 text-white text-xs px-2 py-1 rounded-md shadow-lg z-[10001] dark:bg-black dark:border dark:border-gray-600"
     style="pointer-events: none; white-space: nowrap;">
</div>
<div id="imageViewerModal"
     class="fixed inset-0 bg-black bg-opacity-85 flex items-center justify-center z-[9999] hidden p-4 transition-opacity duration-300 ease-in-out opacity-0">
    <div
        class="relative bg-transparent p-0 rounded-lg shadow-xl max-w-full max-h-full flex items-center justify-center">
        <img id="imageViewerModalImage" src="" alt="${window.translations.imageViewerAltText}"
             class="max-w-[90vw] max-h-[90vh] object-contain rounded-md" loading="lazy">
        <button id="imageViewerModalClose" title="${window.translations.imageViewerCloseTitle}"
                class="absolute top-[-15px] right-[-15px] md:top-2 md:right-2 bg-gray-700 bg-opacity-60 text-white rounded-full p-2 leading-none hover:bg-opacity-90 focus:outline-none z-10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
<style>
    #notification-badge.active-pulse::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: 9999px;
        background-color: #dc2626;
        animation: pulse 1.5s infinite;
        z-index: -1;
    }

    @keyframes pulse {
        0% {
            transform: scale(0.9);
            opacity: 0.7;
        }
        70% {
            transform: scale(1.6);
            opacity: 0;
        }
        100% {
            transform: scale(0.9);
            opacity: 0;
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('imageViewerModal');
        const modalImage = document.getElementById('imageViewerModalImage');
        const closeModalButton = document.getElementById('imageViewerModalClose');
        const tooltipElement = document.getElementById('voteCountTooltip');

        if (modalImage) modalImage.alt = window.translations.imageViewerAltText;
        if (closeModalButton) closeModalButton.title = window.translations.imageViewerCloseTitle;


        if (!modal || !modalImage || !closeModalButton) {
            // console.warn('Image viewer modal elements not found. Zoom functionality will not work.');
        }

        if (!tooltipElement) {
            // console.warn('voteCountTooltip element not found.');
        }

        let currentHoveredButton = null;
        const postsContainer = document.querySelector('main')

        if (postsContainer && tooltipElement && modal && modalImage && closeModalButton) {
            function positionTooltip(mouseX, mouseY) {
                const wasHidden = tooltipElement.classList.contains('hidden');
                if (wasHidden) {
                    tooltipElement.classList.remove('hidden');
                    tooltipElement.style.opacity = '0';
                }

                const tooltipRect = tooltipElement.getBoundingClientRect();

                if (wasHidden) {
                    tooltipElement.classList.add('hidden');
                    tooltipElement.style.opacity = '';
                }


                let x = mouseX + 15;
                let y = mouseY + 15;
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const buffer = 10;

                if (x + tooltipRect.width + buffer > viewportWidth) {
                    x = mouseX - tooltipRect.width - 15;
                }
                if (x < buffer) {
                    x = buffer;
                }

                if (y + tooltipRect.height + buffer > viewportHeight) {
                    y = mouseY - tooltipRect.height - 15;
                }
                if (y < buffer) {
                    y = buffer;
                }

                tooltipElement.style.left = `${x}px`;
                tooltipElement.style.top = `${y}px`;
            }


            postsContainer.addEventListener('mouseover', function (event) {
                const button = event.target.closest('.vote-button');
                if (!button) return;

                currentHoveredButton = button;
                const postArticle = button.closest('article[id^="post-"]');
                if (!postArticle) return;

                let tooltipMessages = [];
                let tooltipBgColor = '#4A5568';
                let showThisTooltip = false;
                let isOwnerVoteContext = false;

                // 1. raw vote counts
                if (button.dataset.tooltipShowCount === 'true') {
                    const optionForCount = button.dataset.option;
                    let count = 0;
                    if (optionForCount === 'option_one') {
                        count = postArticle.dataset.optionOneVotes || 0;
                    } else if (optionForCount === 'option_two') {
                        count = postArticle.dataset.optionTwoVotes || 0;
                    }
                    const votesText = parseInt(count) === 1 ? window.translations.tooltipVoteSingular : window.translations.tooltipVotePlural;
                    tooltipMessages.push(`${count} ${votesText}`);
                    showThisTooltip = true;
                }

                // 2. Owner voted for information
                if (button.dataset.tooltipIsOwnerChoice === 'true' && postArticle.dataset.profileOwnerUsername) {
                    const ownerUsername = postArticle.dataset.profileOwnerUsername;
                    const optionTitle = button.dataset.option === 'option_one' ?
                        postArticle.dataset.optionOneTitle :
                        postArticle.dataset.optionTwoTitle;

                    let message = window.translations.tooltipOwnerVotedForTemplate;
                    message = message.replace(':username', ownerUsername).replace(':optionTitle', optionTitle);
                    tooltipMessages.push(message);

                    showThisTooltip = true;
                    isOwnerVoteContext = true;
                }

                if (showThisTooltip && !isOwnerVoteContext && tooltipMessages.length > 0) {
                    tooltipBgColor = '#4A5568';
                }


                if (showThisTooltip && tooltipMessages.length > 0) {
                    tooltipElement.innerHTML = tooltipMessages.join('<br>');
                    tooltipElement.style.backgroundColor = tooltipBgColor;
                    positionTooltip(event.clientX, event.clientY);
                    tooltipElement.classList.remove('hidden');
                    tooltipElement.style.opacity = '1';
                } else {
                    tooltipElement.classList.add('hidden');
                    currentHoveredButton = null;
                }
            });

            postsContainer.addEventListener('mouseout', function (event) {
                if (currentHoveredButton) {
                    const toElement = event.relatedTarget;
                    if (!currentHoveredButton.contains(toElement)) {
                        tooltipElement.classList.add('hidden');
                        tooltipElement.style.opacity = '0';
                        currentHoveredButton = null;
                    }
                }
            });

            postsContainer.addEventListener('mousemove', function (event) {
                if (currentHoveredButton && !tooltipElement.classList.contains('hidden')) {
                    const button = event.target.closest('.vote-button');
                    if (button === currentHoveredButton) {
                        positionTooltip(event.clientX, event.clientY);
                    } else {
                        tooltipElement.classList.add('hidden');
                        tooltipElement.style.opacity = '0';
                        currentHoveredButton = null;
                    }
                }
            });
        }


        if (modal && modalImage && closeModalButton) {
            function openModal(imageUrl) {
                modalImage.setAttribute('src', imageUrl);
                modal.classList.remove('hidden');
                requestAnimationFrame(() => {
                    modal.classList.remove('opacity-0');
                });
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.add('opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modalImage.setAttribute('src', '');
                }, 300);
                document.body.style.overflow = '';
            }

            document.body.addEventListener('click', function (event) {
                let target = event.target;
                for (let i = 0; i < 3 && target && target !== document.body; i++, target = target.parentNode) {
                    if (target.matches && target.matches('img.zoomable-image')) {
                        event.preventDefault();
                        const fullSrc = target.dataset.fullSrc || target.src;
                        if (fullSrc && fullSrc !== '#' && !fullSrc.endsWith('/#')) {
                            openModal(fullSrc);
                        }
                        return;
                    }
                }
            });

            closeModalButton.addEventListener('click', closeModal);

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        }
    });
</script>

@if (session('scrollToPost'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const postIdToFind = @json(session('scrollToPost'));

            if (postIdToFind) {
                window.addEventListener('load', function () {
                    const postElement = document.getElementById(`post-${postIdToFind}`);

                    if (postElement) {
                        postElement.scrollIntoView({behavior: 'smooth', block: 'center'});

                        postElement.style.transition = 'background-color 0.5s ease';
                        postElement.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';

                        setTimeout(() => {
                            postElement.style.backgroundColor = '';
                        }, 2000);
                    } else {
                        console.error(`[DEBUG] Final attempt failed: Could not find element #post-${postIdToFind} even after page load.`);
                    }
                });
            }
        });
    </script>
@endif

@auth
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const badge = document.getElementById('notification-badge');

            if (!badge) return;

            const fetchNotificationCount = async () => {
                try {
                    const response = await fetch('{{ route("notifications.unread.count") }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        console.error('Failed to fetch notification count.');
                        return;
                    }

                    const data = await response.json();
                    const count = data.count;

                    if (count > 0) {
                        badge.textContent = count > 9 ? '9+' : count;
                        badge.classList.remove('hidden');
                        badge.classList.add('active-pulse');
                    } else {
                        badge.classList.add('hidden');
                        badge.classList.remove('active-pulse');
                    }

                } catch (error) {
                    console.error('Error fetching notification count:', error);
                }
            };

            fetchNotificationCount();

            setInterval(fetchNotificationCount, 30000);
        });
    </script>
@endauth

<script>
    function loadGoogleAds() {
        const adScript = document.createElement('script');
        adScript.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2989575196315667';
        adScript.async = true;
        adScript.crossOrigin = 'anonymous';
        document.body.appendChild(adScript);
    }

    window.addEventListener('load', loadGoogleAds);
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        @if (session('success'))
        window.showToast("{{ session('success') }}", 'success');
        @endif

        @if (session('error'))
        window.showToast("{{ session('error') }}", 'error');
        @endif

        @if (session('info'))
        window.showToast("{{ session('info') }}", 'info');
        @endif

        @if ($errors->any())
        const errors = {!! json_encode($errors->all()) !!};
        window.showToast(errors.join('<br>'), 'error');
        @endif
    });
</script>

<script>
    (function () {
        const urlFragment = window.location.hash.substring(1);

        if (urlFragment.startsWith('tgAuthResult=')) {
            document.documentElement.style.visibility = 'hidden';

            const isDarkMode = document.documentElement.classList.contains('dark');
            const loader = document.createElement('div');
            loader.textContent = 'Authenticating...';

            const bgColor = isDarkMode ? '#111827' : '#f9fafb';
            const textColor = isDarkMode ? '#d1d5db' : '#4b5563';

            loader.style.cssText = `position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: ${bgColor}; font-size: 1.25rem; color: ${textColor}; font-family: sans-serif; z-index: 9999;`;
            document.body.appendChild(loader);

            try {
                const encodedData = urlFragment.substring('tgAuthResult='.length);
                const jsonString = atob(encodedData);
                const data = JSON.parse(jsonString);
                const queryParams = new URLSearchParams(data).toString();
                const callbackUrl = "{{ route('auth.telegram.callback') }}";

                window.location.replace(`${callbackUrl}?${queryParams}`);
            } catch (error) {
                console.error('Failed to process Telegram auth data:', error);
                window.location.href = "{{ route('login') }}?error=telegram_processing_failed";
            }
        }
    })();
</script>

</body>
</html>
