@extends('layouts.app')

@section('title', __('messages.about_us'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-lg font-semibold mb-1" style="font-size: 1.125rem; font-weight: 600;">{{ __('messages.about.who_we_are_heading') }}</h2>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.about.who_we_are_text') }}
            </p>

            <h2 class="text-lg font-semibold mb-1 mt-6" style="font-size: 1.125rem; font-weight: 600;">{{ __('messages.about.our_mission_heading') }}</h2>
            <p class="text-gray-600 text-sm">
                {{ __('messages.about.our_mission_text') }}
            </p>
        </div>
    </div>
@endsection
