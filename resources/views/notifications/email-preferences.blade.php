@extends('layouts.app')

@section('title', __("mail.page.{$state}_title") . ' - GOAT.uz')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.1)] p-6 text-center">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ __("mail.page.{$state}_title") }}</h1>
        <p class="text-gray-600 dark:text-gray-300 mb-6">{{ __("mail.page.{$state}_text") }}</p>

        @if ($state === 'confirm')
            <form method="POST" action="{{ $unsubscribeUrl }}" class="mb-4">
                <button type="submit" class="w-full px-6 py-2 bg-blue-800 dark:bg-blue-600 text-white rounded-md hover:bg-blue-900 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    {{ __('mail.page.confirm_button') }}
                </button>
            </form>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">{{ __('mail.page.keep_link') }}</a>
        @elseif (in_array($state, ['done', 'already']))
            <form method="POST" action="{{ $resubscribeUrl }}" class="mb-4">
                <button type="submit" class="w-full px-6 py-2 bg-blue-800 dark:bg-blue-600 text-white rounded-md hover:bg-blue-900 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    {{ __('mail.page.resubscribe_button') }}
                </button>
            </form>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">{{ __('mail.page.home') }}</a>
        @else
            <a href="{{ route('home') }}" class="text-sm text-blue-800 dark:text-blue-400 hover:underline">{{ __('mail.page.home') }}</a>
        @endif
    </div>
@endsection
