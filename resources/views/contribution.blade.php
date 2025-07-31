@extends('layouts.app')

@section('title', __('messages.contribution.title'))

@push('schema')
    <script type="application/ld+json">
        {
          "@@context": "https://schema.org",
          "@@graph": [
            {
              "@@type": "WebPage",
              "name": "{{ __('messages.contribution.title') }}",
      "description": "{{ __('messages.contribution.intro_p1') }}",
      "url": "{{ route('contribution') }}"
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
        "name": "{{ __('messages.contribution.title') }}"
      }]
    }
  ]
}
    </script>
@endpush

@section('content')
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4">
        <div class="p-6 sm:p-8">
            <h2 class="text-lg font-semibold mb-2" style="font-size: 1.125rem; font-weight: 600;">{{ __('messages.contribution.main_heading') }}</h2>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('messages.contribution.intro_p1') }}
            </p>
            <p class="text-gray-600 text-sm mb-6">
                {{ __('messages.contribution.intro_p2') }}
            </p>

            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.contribution.ways_to_contribute_heading') }}</h3>
                <p class="text-gray-600 text-sm mb-4">
                    {{ __('messages.contribution.ways_to_contribute_p1') }}
                </p>

                <div class="mb-4">
                    <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.contribution.bugs_heading') }}</h3>
                    <p class="text-gray-600 text-sm">
                        {{ __('messages.contribution.bugs_text') }}
                        <a href="https://github.com/umaarov/goat-dev/issues" target="_blank"
                           class="text-blue-600 hover:underline">{{ __('messages.contribution.issue_tracker_link') }}</a>.
                    </p>
                </div>

                <div class="mb-6">
                    <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.contribution.enhancements_heading') }}</h3>
                    <p class="text-gray-600 text-sm">
                        {{ __('messages.contribution.enhancements_text') }}
                    </p>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.contribution.pr_heading') }}</h3>
                <p class="text-gray-600 text-sm mb-4">
                    {{ __('messages.contribution.pr_intro') }}
                </p>
                <ul class="list-decimal list-inside text-gray-600 text-sm space-y-2">
                    <li>{{ __('messages.contribution.pr_step_1') }}</li>
                    <li>{{ __('messages.contribution.pr_step_2') }}</li>
                    <li>{!! __('messages.contribution.pr_step_3') !!}</li>
                </ul>
            </div>

            <div class="border-t border-gray-200 pt-6 mt-6">
                <h3 class="text-md font-semibold mb-1" style="font-size: 1rem; font-weight: 600;">{{ __('messages.contribution.coc_heading') }}</h3>
                <p class="text-gray-600 text-sm">
                    {{ __('messages.contribution.coc_text') }}
                    <a href="https://github.com/umaarov/goat-dev/blob/master/CODE_OF_CONDUCT.md"
                       class="text-blue-600 hover:underline">{{ __('messages.contribution.coc_link_text') }}</a>.
                </p>
            </div>

            <div class="mt-8 text-center">
                <p class="text-gray-700 font-semibold">
                    {{ __('messages.contribution.thanks_text') }}
                </p>
            </div>
        </div>
    </div>
@endsection
