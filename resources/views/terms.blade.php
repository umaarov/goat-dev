@extends('layouts.app')

@section('title', __('messages.terms_of_use'))

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h5 class="text-lg font-semibold mb-2">{{ __('messages.terms_of_use') }}</h5>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.intro_text') }}
            </p>

            <h6 class="text-md font-semibold mb-1">{{ __('messages.terms.section1_heading') }}</h6>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section1_text') }}
            </p>

            <h6 class="text-md font-semibold mb-1">{{ __('messages.terms.section2_heading') }}</h6>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section2_text') }}
            </p>

            <h6 class="text-md font-semibold mb-1">{{ __('messages.terms.section3_heading') }}</h6>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section3_text') }}
            </p>

            <h6 class="text-md font-semibold mb-1">{{ __('messages.terms.section4_heading') }}</h6>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.terms.section4_text') }}
            </p>

            <h6 class="text-md font-semibold mb-1">{{ __('messages.terms.section5_heading') }}</h6>
            <p class="text-gray-600 text-sm">
                {{ __('messages.terms.section5_text') }}
            </p>
        </div>
    </div>
@endsection
