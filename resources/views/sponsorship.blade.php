@extends('layouts.app')

@section('title', __('messages.sponsorship'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-lg font-semibold mb-2" style="font-size: 1.125rem; font-weight: 600;">{{ __('messages.sponsorship.opportunities_heading') }}</h2>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.sponsorship.opportunities_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.ads.reach_out_heading') }}</h3>
            <p class="text-gray-600 text-sm">
                {{ __('messages.ads.reach_out_prompt') }} <a href="mailto:info@goat.uz" class="text-blue-600 hover:underline">info@goat.uz</a>
                {{ __('messages.sponsorship.reach_out_for_proposals') }}
            </p>
        </div>
    </div>
@endsection
