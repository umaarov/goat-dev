@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', $queryTerm ? __('messages.search_results.title_with_query', ['queryTerm' => e($queryTerm)]) : __('messages.search_results.title_default'))
@section('meta_robots', 'noindex, follow')

@push('schema')
    @if ($queryTerm)
        <script type="application/ld+json">
            {
              "@@context": "https://schema.org",
              "@@graph": [
                {
                  "@@type": "SearchResultsPage",
                  "name": "{{ __('messages.search_results.title_with_query', ['queryTerm' => e($queryTerm)]) }}",
      "description": "Search results for '{{ e($queryTerm) }}' on GOAT.uz, showing matching polls and user profiles.",
      "url": "{{ url()->full() }}",
      "mainEntity": {
        "@@type": "ItemList",
        "name": "Search Results",
        "numberOfItems": {{ $users->count() + $posts->count() }},
        "itemListElement": [
            @foreach($users as $user)
                {
                    "@@type": "ListItem",
                    "position": {{ $loop->index + 1 }},
                "item": {
                    "@@type": "Person",
                    "@@id": "{{ route('profile.show', $user->username) }}",
                    "name": "{{ $user->username }}"
                }
            }{{ ($loop->last && $posts->isEmpty()) ? '' : ',' }}
            @endforeach
            @foreach($posts as $post)
                {
                    "@@type": "ListItem",
                    "position": {{ $users->count() + $loop->index + 1 }},
                "item": {
                    "@@type": "SocialMediaPosting",
                    "@@id": "{{ route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]) }}",
                    "headline": "{{ addslashes($post->question) }}"
                }
            }{{ $loop->last ? '' : ',' }}
            @endforeach
            ]
          },
          "isPartOf": {
            "@@id": "{{ config('app.url') }}#website"
      }
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
        "name": "Search"
      }]
    }
  ]
}
        </script>
    @endif
@endpush

@section('content')
    <div class="flex flex-col items-center justify-center w-full">
        <form action="{{ route('search') }}" method="GET" class="w-full max-w-xl mx-auto">
            <div class="relative">
                <input
                    type="search"
                    name="q"
                    value="{{ old('q', $queryTerm) }}"
                    placeholder="{{ __('messages.search_results.placeholder') }}"
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-2xl transition duration-150 ease-in-out dark:bg-gray-800 dark:text-gray-50 dark:placeholder-gray-400"
                    autocomplete="off"
                />
                <button type="submit"
                        class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                    <svg class="w-5 h-5" viewBox="0 -0.5 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                              d="M5.5 11.1455C5.49956 8.21437 7.56975 5.69108 10.4445 5.11883C13.3193 4.54659 16.198 6.08477 17.32 8.79267C18.4421 11.5006 17.495 14.624 15.058 16.2528C12.621 17.8815 9.37287 17.562 7.3 15.4895C6.14763 14.3376 5.50014 12.775 5.5 11.1455Z"
                              stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M15.989 15.4905L19.5 19.0015" stroke="currentColor" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </form>

        @if ($queryTerm)
            <div class="w-full max-w-4xl mt-6">

                {{-- 1. USERS RESULTS SECTION --}}
                @if ($users->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        @foreach($users as $user)
                            @php
                                $profilePic = $user->profile_picture
                                    ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                                        ? $user->profile_picture
                                        : asset('storage/' . $user->profile_picture))
                                    : asset('images/default-pfp.png');

                                $isVerified = in_array($user->username, ['goat', 'umarov']);
                            @endphp
                            <a href="{{ route('profile.show', ['username' => $user->username]) }}"
                               class="flex items-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-500 transition duration-200">

                                <img src="{{ $profilePic }}"
                                     alt="{{ __('messages.profile.alt_profile_picture', ['username' => $user->username]) }}"
                                     class="h-12 w-12 rounded-full object-cover border border-gray-200 dark:border-gray-600 cursor-pointer zoomable-image flex-shrink-0"
                                     data-full-src="{{ $profilePic }}">

                                <div class="ml-4 flex-1 min-w-0">
                                    <div class="flex items-center">
                                        <p class="text-md font-semibold text-gray-900 dark:text-gray-100 truncate"
                                           title="{{ $user->first_name }}">
                                            {{ $user->first_name }}
                                        </p>

                                        @if($isVerified)
                                            <span class="ml-1 flex-shrink-0"
                                                  title="{{ __('messages.profile.verified_account') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500"
                                                     viewBox="0 0 20 20"
                                                     fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                          d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                          clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 truncate"
                                       title="{{ $user->username }}">
                                        {{ $user->username }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- 2. POSTS RESULTS SECTION --}}
                @if ($posts->isNotEmpty())
                    <div id="posts-wrapper">
                        <div id="posts-container" class="space-y-4">
                            @foreach ($posts as $post)
                                @include('partials.post-card', ['post' => $post])

                                @if (($loop->iteration % 6) == 0)
                                    <div class="w-full mb-4">
                                        <script async
                                                src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2889575196315667"
                                                crossorigin="anonymous"></script>
                                        <ins class="adsbygoogle"
                                             style="display:block"
                                             data-ad-format="fluid"
                                             data-ad-layout-key="-6t+ed+2i-1n-4w"
                                             data-ad-client="ca-pub-2889575196315667"
                                             data-ad-slot="7674157999"></ins>
                                        <script>
                                            (function () {
                                                const adIns = document.currentScript.previousElementSibling;
                                                const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                                                adIns.setAttribute('data-ad-ui-theme', theme);
                                                (adsbygoogle = window.adsbygoogle || []).push({});
                                            })();
                                        </script>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Infinite Scroll Trigger and Loading Indicator --}}
                        <div id="infinite-scroll-trigger"></div>

                        <div id="loading-indicator" class="hidden text-center py-4">
                            @include('partials.post-card-shimmer')
                        </div>
                    </div>
                @endif

                @if ($users->isEmpty() && $posts->isEmpty())
                    <div class="text-center mt-2 mb-8 text-gray-600 dark:text-gray-400">
                        <p>{{ __('messages.search_results.no_results_found', ['queryTerm' => e($queryTerm)]) }}</p>
                        <p>{{ __('messages.search_results.try_different_keywords') }}</p>
                    </div>
                @endif
            </div>
        @else
            <p class="mt-2 mb-8 text-center text-gray-600 dark:text-gray-400">{{ __('messages.search_results.enter_term_prompt') }}</p>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const initialPostsExist = {{ $posts->count() > 0 ? 'true' : 'false' }};
            if (!initialPostsExist) return;

            const postContainer = document.getElementById('posts-container');
            const trigger = document.getElementById('infinite-scroll-trigger');
            const loadingIndicator = document.getElementById('loading-indicator');
            const queryTerm = '{{ $queryTerm ?? '' }}';

            let nextPage = 2;
            let isLoading = false;
            let hasMorePages = {{ $posts->hasMorePages() ? 'true' : 'false' }};

            if (!hasMorePages) {
                trigger.style.display = 'none';
            }

            function loadVisibleAds() {
                let adSlots = document.querySelectorAll('ins.adsbygoogle:not([data-ad-status="filled"])');
                adSlots.forEach(adIns => {
                    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                    adIns.setAttribute('data-ad-ui-theme', theme);
                    try {
                        (adsbygoogle = window.adsbygoogle || []).push({});
                    } catch (e) {
                        console.error("AdSense push error: ", e);
                    }
                });
            }

            const loadMorePosts = async () => {
                if (isLoading || !hasMorePages) return;

                isLoading = true;
                loadingIndicator.classList.remove('hidden');

                const url = `{{ route('search') }}?page=${nextPage}&q=${encodeURIComponent(queryTerm)}`;

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    });

                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                    const data = await response.json();

                    if (data.html && data.html.trim().length > 0) {
                        postContainer.insertAdjacentHTML('beforeend', data.html);

                        loadVisibleAds();

                        document.dispatchEvent(new Event('posts-loaded'));
                        nextPage++;
                        hasMorePages = data.hasMorePages;

                        if (!hasMorePages) {
                            trigger.style.display = 'none';
                        }
                    } else {
                        hasMorePages = false;
                        trigger.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Error loading more search results:', error);
                    trigger.style.display = 'none';
                } finally {
                    isLoading = false;
                    loadingIndicator.classList.add('hidden');
                }
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMorePosts();
                }
            }, {
                rootMargin: '400px',
            });

            if (hasMorePages) {
                observer.observe(trigger);
            }
        });
    </script>
@endpush

