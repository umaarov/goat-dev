@extends('layouts.app')

@section('title', __('messages.ads'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h5 class="text-lg font-semibold mb-2">{{ __('messages.ads.advertisement_heading') }}</h5>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.ads.advertisement_text') }}
            </p>

            <h6 class="text-md font-semibold mb-1">{{ __('messages.ads.reach_out_heading') }}</h6>
            <p class="text-gray-600 text-sm">
                {{ __('messages.ads.reach_out_prompt') }} <a href="mailto:info@goat.uz" class="text-blue-600 hover:underline">info@goat.uz</a>
                {{ __('messages.ads.reach_out_get_started') }}
            </p>
        </div>
    </div>
@endsection
