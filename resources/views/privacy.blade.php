@php use Carbon\Carbon; @endphp
@extends('layouts.app')

@section('title', __('messages.privacy_policy.title'))
@section('meta_description', __('messages.privacy_policy.meta_description'))

@push('schema')
    <script type="application/ld+json">
        {
          "@@context": "https://schema.org",
          "@@graph": [
            {
              "@@type": "WebPage",
              "name": "{{ __('messages.privacy_policy.title') }}",
              "description": "{{ __('messages.privacy_policy.meta_description') }}",
              "url": "{{ route('privacy') }}",
              "dateModified": "{{ Carbon::now()->toIso8601String() }}"
            },
            {
              "@@type": "BreadcrumbList",
              "itemListElement": [{
                "@@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "{{ route('home') }}"
              },{
                "@@type": "ListItem",
                "position": 2,
                "name": "{{ __('messages.privacy_policy.title_nav') }}"
              }]
            }
          ]
        }
    </script>
@endpush

@section('content')
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6">
            <h2 class="text-lg font-semibold mb-2" style="font-size: 1.125rem; font-weight: 600;">
                {{ __('messages.privacy_policy.title_nav') }}
            </h2>

            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.privacy_policy.intro_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">
                {{ __('messages.privacy_policy.section1_heading') }}
            </h3>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.privacy_policy.section1_text') }}
            </p>

            <div id="ezoic-privacy-policy-embed"></div>

            <h3 class="text-md font-semibold mb-1 mt-4" style="font-size: 1rem; font-weight: 600;">
                {{ __('messages.privacy_policy.section2_heading') }}
            </h3>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.privacy_policy.section2_text') }}
            </p>

            <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">
                {{ __('messages.privacy_policy.section3_heading') }}
            </h3>
            <p class="text-gray-600 text-sm">
                {{ __('messages.privacy_policy.section3_text') }}
            </p>
        </div>
    </div>
@endsection
