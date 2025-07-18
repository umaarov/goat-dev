@extends('layouts.app')

@php
    $path = request()->path();
    $isUserProfile = preg_match('/^@[\w\-\.]+$/', $path);
    $pageTitle = $isUserProfile ? __('messages.error.404.user_not_found_title') : __('messages.error.404.page_title');
@endphp

@section('title', $pageTitle)
@section('meta_robots', 'noindex, follow')

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            @if($isUserProfile)
                <h1 class="text-2xl font-semibold mb-3">{{ __('messages.error.404.heading') }}</h1>
                <p class="text-gray-600 mb-6">{{ __('messages.error.404.user_not_found_message') }}</p>
                <div
                    class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm">
                    <img src="{{ asset('images/lost_goat.jpg') }}" alt="{{ __('messages.error.404.lost_goat_alt') }}"
                         class="h-64 w-full object-cover rounded-lg">
                </div>
            @else
                <h1 class="text-2xl font-semibold mb-3">{{ __('messages.error.404.heading') }}</h1>
                <p class="text-gray-600 mb-6">{{ __('messages.error.404.page_not_found_message') }}</p>
                <div
                    class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm">
                    <img src="{{ asset('images/lost_goat.jpg') }}" alt="{{ __('messages.error.404.lost_goat_alt') }}"
                         class="h-64 w-full object-cover rounded-lg">
                </div>
            @endif
        </div>
    </div>
@endsection
