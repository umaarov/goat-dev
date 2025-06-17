@extends('layouts.app')

@section('title', __('messages.terms_of_use'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-lg font-semibold mb-2" style="font-size: 1.125rem; font-weight: 600;">{{ __('messages.terms_of_use') }}</h2>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.intro_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.terms.section1_heading') }}</h3>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section1_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.terms.section2_heading') }}</h3>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section2_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.terms.section3_heading') }}</h3>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section3_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.terms.section4_heading') }}</h3>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section4_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.terms.section5_heading') }}</h3>
            <p class="text-gray-600 text-sm">
                {{ __('messages.terms.section5_text') }}
            </p>
        </div>
    </div>
@endsection
