@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
@endphp
@extends('layouts.app')

@section('title', __('messages.profile.title', ['username' => $user->username]))
@section('meta_description', __('messages.profile.meta_description', ['username' => $user->username]))

@section('content')
    <div class="max-w-3xl mx-auto">
        @php
            $hasBackground = !empty($headerBackgroundUrl);
        @endphp
        <div
{{--            class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] border border-gray-100 overflow-hidden mb-6">--}}
                class="relative rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] border border-gray-100 overflow-hidden mb-6 @if(!$hasBackground) bg-white @endif">

            {{-- Background Image & Gradient Overlay Layer --}}
            @if($hasBackground)
                <div class="absolute inset-0 z-0">
                    <img src="{{ $headerBackgroundUrl }}"
                         alt="{{ __('messages.profile.alt_header_background', ['username' => $user->username]) }}"
                         class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-black/40"></div>
                </div>
            @endif
            <div class="relative z-10 p-6">
                <div class="flex items-start">
                    @php
                        $profilePic = $user->profile_picture
                            ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                                ? $user->profile_picture
                                : Storage::url($user->profile_picture))
                            : asset('images/default-pfp.png');

                        $isVerified = in_array($user->username, ['goat', 'umarov']);
                        $displayName = ($user->first_name || $user->last_name) ? trim($user->first_name . ' ' . $user->last_name) : "@".$user->username;
                        $showSubUsername = ($user->first_name || $user->last_name);
                    @endphp
                    <div class="relative flex-shrink-0">
                        @php
                            $earnedBadgeKeys = !empty($userBadges) ? array_keys($userBadges) : [];
                        @endphp

                        <div id="badge-container" data-earned-badges="{{ json_encode($earnedBadgeKeys) }}">
                            <canvas id="badge-canvas">
                            </canvas>
                        </div>
                        <img src="{{ $profilePic }}"
                             alt="{{ __('messages.profile.alt_profile_picture', ['username' => $user->username]) }}"
                             class="h-24 w-24 rounded-full object-cover border-2 {{ $hasBackground ? 'border-white/50' : 'border-gray-200' }} cursor-pointer zoomable-image"
                             data-full-src="{{ $profilePic }}">
                    </div>

                    {{-- Info Block --}}
                    <div class="ml-4 flex-1 flex flex-col">
                        <div>
                            {{-- Name, Verified, and Moderator Badge --}}
                            <div class="flex flex-wrap items-center gap-y-1">
                                <div class="flex items-center">
                                    <h1 class="text-2xl font-semibold {{ $hasBackground ? 'text-white' : 'text-gray-800' }}" style="font-size: 1.5rem; line-height: 2rem; font-weight: 600;">{{ $displayName }}</h1>
                                    @if($isVerified)
                                        <span class="ml-1.5" title="{{ __('messages.profile.verified_account') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>

                                @if($user->username === 'goat')
                                    <span class="ml-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $hasBackground ? 'bg-green-400/25 text-green-300' : 'bg-green-100 text-green-800' }}">
                                        Moderator
                                    </span>
                                @endif
                            </div>

                            {{-- Username and Join Date --}}
                            @if($showSubUsername)
                                <p class="{{ $hasBackground ? 'text-gray-300' : 'text-gray-600' }} text-sm mt-1.5">{{ "@".$user->username }}</p>
                            @endif
                            <p class="{{ $hasBackground ? 'text-gray-400' : 'text-gray-500' }} text-xs mt-1">{{ __('messages.profile.joined_label') }} <time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->format('M d, Y') }}</time></p>
                        </div>



                        {{-- Rank Badge Logic & Display --}}
                        @if (!empty($userBadges))
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @foreach ($userBadges as $badge)
                                    <span title="{{ $badge['name'] }} - Rank: #{{ $badge['rank'] }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badge['classes'] }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1.5 -ml-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        <span>{{ $badge['name'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Stats Section --}}
                        <div class="mt-3 flex items-center space-x-6 text-sm">
                            <div>
                                <span class="font-semibold {{ $hasBackground ? 'text-white' : 'text-gray-800' }}">{{ number_format($user->posts_count) }}</span>
                                <span class="{{ $hasBackground ? 'text-gray-300' : 'text-gray-500' }}">{{ trans_choice('messages.profile.posts_stat_label', $user->posts_count) }}</span>
                            </div>
                            <div>
                                <span class="font-semibold {{ $hasBackground ? 'text-white' : 'text-gray-800' }}">{{ number_format($totalVotesOnUserPosts) }}</span>
                                <span class="{{ $hasBackground ? 'text-gray-300' : 'text-gray-500' }}">{{ trans_choice('messages.profile.votes_collected_stat_label', $totalVotesOnUserPosts) }}</span>
                            </div>
                        </div>

                        {{-- External Links Section --}}
                        @if(!empty($user->external_links) && count(array_filter($user->external_links)) > 0)
                            <div class="mt-3">
                                <div class="flex flex-wrap gap-2 items-center">
                                    @foreach($user->external_links as $link_url)
                                        @if(!empty($link_url))
                                            @php
                                                $iconSvgHtml = '';
                                                $displayText = '';
                                                $host = strtolower(parse_url($link_url, PHP_URL_HOST));
                                                $cleanedHost = preg_replace('/^www\./', '', $host);

                                                if (
                                                    ($host === 't.me' || Str::endsWith($host, '.t.me')) ||
                                                    ($host === 'telegram.me' || Str::endsWith($host, '.telegram.me'))
                                                ) {
                                                    $iconSvgHtml = '<svg class="h-5 w-5 text-sky-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19.2,4.4L2.9,10.7c-1.1,0.4-1.1,1.1-0.2,1.3l4.1,1.3l1.6,4.8c0.2,0.5,0.1,0.7,0.6,0.7c0.4,0,0.6-0.2,0.8-0.4c0.1-0.1,1-1,2-2l4.2,3.1c0.8,0.4,1.3,0.2,1.5-0.7l2.8-13.1C20.6,4.6,19.9,4,19.2,4.4z M17.1,7.4l-7.8,7.1L9,17.8L7.4,13l9.2-5.8C17,6.9,17.4,7.1,17.1,7.4z"/></svg>';
                                                    $displayText = __('messages.profile.link_telegram');
                                                } elseif (
                                                    ($host === 'twitter.com' || Str::endsWith($host, '.twitter.com')) ||
                                                    ($host === 'x.com' || Str::endsWith($host, '.x.com'))
                                                ) {
                                                    $iconSvgHtml = '<svg class="h-5 w-5 text-gray-700 dark:text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
                                                    $displayText = __('messages.profile.link_twitter');
                                                } elseif ($host === 'instagram.com' || Str::endsWith($host, '.instagram.com')) {
                                                    $iconSvgHtml = '<svg class="h-5 w-5 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.316.011 7.053.069 2.59.284.287 2.59.07 7.053.011 8.316 0 8.741 0 12c0 3.259.011 3.684.069 4.947.217 4.46 2.522 6.769 7.053 6.984 1.267.058 1.692.069 4.947.069 3.259 0 3.684-.011 4.947-.069 4.46-.217 6.769-2.522 6.984-7.053.058-1.267.069-1.692.069-4.947 0-3.259-.011-3.684-.069-4.947-.217-4.46-2.522-6.769-7.053-6.984C15.684.011 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>';
                                                    $displayText = __('messages.profile.link_instagram');
                                                } elseif ($host === 'facebook.com' || Str::endsWith($host, '.facebook.com')) {
                                                    $iconSvgHtml = '<svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12c6.627 0 12-5.373 12-12S18.627 0 12 0zm3.055 8.181h-1.717c-.594 0-.708.282-.708.695v.978h2.399l-.311 2.445h-2.088V20.5h-2.523v-8.199H8.222V9.854h1.887V8.69c0-1.871 1.142-2.89 2.813-2.89a15.868 15.868 0 011.67.087v2.204h-.986c-.908 0-1.084.432-1.084 1.065v.025z"/></svg>';
                                                    $displayText = __('messages.profile.link_facebook');
                                                } elseif ($host === 'linkedin.com' || Str::endsWith($host, '.linkedin.com')) {
                                                    $iconSvgHtml = '<svg class="h-5 w-5 text-blue-700" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/></svg>';
                                                    $displayText = __('messages.profile.link_linkedin');
                                                } elseif ($host === 'github.com' || Str::endsWith($host, '.github.com')) {
                                                    $iconSvgHtml = '<svg class="h-4 w-5 text-gray-800 dark:text-gray-200" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.026 2.747-1.026.546 1.379.201 2.398.098 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.922.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.001 10.001 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>';
                                                    $displayText = __('messages.profile.link_github');
                                                } else {
                                                    $iconSvgHtml = '<svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24"><path d="M13.0601 10.9399C15.3101 13.1899 15.3101 16.8299 13.0601 19.0699C10.8101 21.3099 7.17009 21.3199 4.93009 19.0699C2.69009 16.8199 2.68009 13.1799 4.93009 10.9399" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>  <path d="M10.59 13.4099C8.24996 11.0699 8.24996 7.26988 10.59 4.91988C12.93 2.56988 16.73 2.57988 19.08 4.91988C21.43 7.25988 21.42 11.0599 19.08 13.4099" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                                                    $displayText = Str::limit($cleanedHost, 30);
                                                }
                                            @endphp
                                            <a href="{{ $link_url }}" target="_blank" rel="noopener noreferrer nofollow"
                                               title="{{ $displayText }} - {{ $link_url }}"
                                               class="inline-flex items-center justify-center w-9 h-9 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 p-2 rounded-full transition-colors duration-150 shadow-sm border border-gray-200 dark:border-gray-600">
                                                {!! $iconSvgHtml !!}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($isOwnProfile)
                            <div class="mt-4">
                                <a href="{{ route('profile.edit') }}"
                                   class="inline-block px-4 py-2 rounded-md focus:outline-none focus:ring-2 text-sm font-semibold {{ $hasBackground ? 'bg-white/90 text-black hover:bg-white focus:ring-blue-300' : 'bg-blue-800 text-white hover:bg-blue-900 focus:ring-blue-500' }}">
                                    {{ __('messages.profile.edit_profile_button') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4 border-b border-gray-200">
        <div class="flex mx-auto items-center justify-between">
            <button id="load-my-posts" data-url="{{ route('profile.posts.data', $user->username) }}"
                    class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                {{ $isOwnProfile ? __('messages.profile.my_posts_tab') : __('messages.profile.users_posts_tab', ['username' => $user->username]) }}
{{--                {{ __('messages.profile.my_posts_tab') }}--}}
                <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-800 transition-all duration-300"
                      id="my-posts-indicator"></span>
            </button>

            {{-- Voted Posts tab --}}
            @if ($isOwnProfile || $user->show_voted_posts_publicly)
                <button id="load-voted-posts" data-url="{{ route('profile.voted.data', $user->username) }}"
                        class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                    {{ __('messages.profile.voted_posts_tab') }}
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-transparent transition-all duration-300"
                          id="voted-posts-indicator"></span>
                </button>
            @endif

            {{-- Ratings Tab --}}
            <a href="{{ route('rating.index') }}"
               class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                <span>{{ __('messages.ratings.nav_title') }}</span>
            </a>
        </div>
    </div>

    <div id="posts-container" class="space-y-4">
        @for ($i = 0; $i < 3; $i++)
            @include('partials.post-card-shimmer')
        @endfor
    </div>

    <div id="profile-infinite-scroll-trigger" class="h-1"></div>

    <div id="profile-loading-indicator" class="hidden">
        @include('partials.post-card-shimmer')
    </div>

    <div id="badge-enlarged-container" style="display: none;">
        <canvas id="enlarged-badge-canvas"></canvas>

        <div id="enlarged-badge-info">
            <h2 id="enlarged-badge-name"></h2>
            <p id="enlarged-badge-context"></p>
            <hr class="info-divider">
            <p id="enlarged-badge-description"></p>
            <div id="enlarged-badge-stats">
                <div class="stat-item">
                    <span class="stat-label">Rarity</span>
                    <span id="stat-rarity" class="stat-value"></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Origin</span>
                    <span id="stat-origin" class="stat-value"></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Type</span>
                    <span id="stat-type" class="stat-value"></span>
                </div>
            </div>
        </div>

        <button id="close-enlarged-badge">&times;</button>
    </div>

@endsection

@push('scripts')
    <script>
        // Pass the necessary PHP variables to JavaScript
        window.i18n = {
            profile: {
                js: {
                    login_to_see_posts: `{!! addslashes(__('messages.profile.js.login_to_see_posts')) !!}`
                }
            }
        };

        window.profileUsername = '{{ addslashes($user->username) }}';
    </script>
    <x-shared-post-scripts />
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('scrollToPost'))
            scrollToPost({{ session('scrollToPost') }});
            @endif
            const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
            commentsSections.forEach(section => {
                if (!section.classList.contains('comments-section')) {
                    section.classList.add('comments-section');
                }
                if (!section.classList.contains('hidden')) {
                    section.classList.add('hidden');
                }
                section.classList.remove('active');
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const postsContainer = document.getElementById('posts-container');
            const myPostsButton = document.getElementById('load-my-posts');
            const myPostsIndicator = document.getElementById('my-posts-indicator');
            const votedPostsButton = document.getElementById('load-voted-posts');
            const votedPostsIndicator = votedPostsButton ? document.getElementById('voted-posts-indicator') : null;
            const scrollTrigger = document.getElementById('profile-infinite-scroll-trigger');
            const loadingIndicator = document.getElementById('profile-loading-indicator');


            const shimmerHTML = `
                @for ($i = 0; $i < 3; $i++)
            @include('partials.post-card-shimmer')
            @endfor
            `;

            const buttons = [myPostsButton, votedPostsButton].filter(btn => btn != null);
            const indicators = [myPostsIndicator, votedPostsIndicator].filter(ind => ind != null);

            let currentPage = {};
            let isLoading = {};
            let hasMorePages = {};
            let activeTabData = { url: null, type: null };
            let observer;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // initializeZoomableImages();

            function setActiveTab(activeButton) {
                buttons.forEach(btn => {
                    btn.classList.remove('text-blue-800', 'font-semibold');
                    btn.classList.add('text-gray-700');
                });

                indicators.forEach(ind => {
                    ind.classList.remove('bg-blue-800');
                    ind.classList.add('bg-transparent');
                });

                if (activeButton) {
                    activeButton.classList.remove('text-gray-700');
                    activeButton.classList.add('text-blue-800', 'font-semibold'); // Active state

                    const indicatorIdSuffix = activeButton.id.startsWith('load-my-posts') ? 'my-posts-indicator' : 'voted-posts-indicator';
                    const activeIndicator = document.getElementById(indicatorIdSuffix);

                    if (activeIndicator) {
                        activeIndicator.classList.remove('bg-transparent');
                        activeIndicator.classList.add('bg-blue-800');
                    }
                }
            }

            function initializeProgressiveImages(container = document) {
                const imagesToLoad = container.querySelectorAll('.progressive-image[data-src]');
                if (imagesToLoad.length === 0) return;

                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const image = entry.target;
                            const highResSrc = image.dataset.src;

                            if (highResSrc) {
                                image.src = highResSrc;
                                image.addEventListener('load', () => {
                                    image.classList.add('loaded');
                                });
                                image.removeAttribute('data-src');
                            }
                            observer.unobserve(image);
                        }
                    });
                }, { rootMargin: '0px 0px 200px 0px' });

                imagesToLoad.forEach(img => imageObserver.observe(img));
            }

            async function loadPosts(url, type, loadMore = false) {
                if (isLoading[type]) return;
                if (loadMore && (activeTabData.type !== type || !hasMorePages[type])) return;

                isLoading[type] = true;

                if (!loadMore) {
                    currentPage[type] = 1;
                    hasMorePages[type] = true;
                    postsContainer.innerHTML = shimmerHTML;
                    Object.keys(isLoading).forEach(key => { if (key !== type) isLoading[key] = false; });
                    activeTabData = { url, type };
                    if (observer) observer.unobserve(scrollTrigger);
                } else {
                    currentPage[type]++;
                    loadingIndicator.classList.remove('hidden');
                    if (observer) observer.unobserve(scrollTrigger);
                }

                const fetchUrl = `${url}?page=${currentPage[type]}`;

                try {
                    const response = await fetch(fetchUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });

                    if (response.status === 401) {
                        const loginMessage = (window.i18n?.profile?.js?.login_to_see_posts || 'Please log in to see posts by :username.').replace(':username', window.profileUsername || 'this user');
                        postsContainer.innerHTML = `<p class="text-gray-500 text-center py-8">${loginMessage}</p>`;
                        hasMorePages[type] = false;
                        return;
                    }

                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

                    const data = await response.json();
                    const noPostsMessage = window.i18n?.profile?.js?.no_posts_found || 'No pozsts were found.';

                    if (!loadMore) {
                        postsContainer.innerHTML = data.html || `<p class="text-gray-500 text-center py-8">${noPostsMessage}</p>`;
                    } else {
                        postsContainer.insertAdjacentHTML('beforeend', data.html || '');
                    }

                    document.dispatchEvent(new CustomEvent('posts-loaded'));

                    hasMorePages[type] = data.hasMorePages;

                    if (hasMorePages[type]) {
                        observer.observe(scrollTrigger);
                    }

                    if (postsContainer.children.length === 0 || (postsContainer.children.length === 1 && postsContainer.children[0].tagName === 'P')) {
                        postsContainer.innerHTML = `<p class="text-gray-500 text-center py-8">${noPostsMessage}</p>`;
                        if(observer) observer.unobserve(scrollTrigger);
                    }

                } catch (error) {
                    console.error('Error loading posts:', error);
                    if (!loadMore) {
                        const errorMessage = window.i18n?.profile?.js?.error_loading_posts || 'An error occurred while loading posts.';
                        postsContainer.innerHTML = `<p class="text-red-500 text-center py-8">${errorMessage}</p>`;
                    }
                } finally {
                    isLoading[type] = false;
                    if (loadMore) {
                        loadingIndicator.classList.add('hidden');
                    }
                }
            }

            observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !isLoading[activeTabData.type]) {
                    if (activeTabData.url && activeTabData.type) {
                        loadPosts(activeTabData.url, activeTabData.type, true);
                    }
                }
            }, {
                rootMargin: '400px',
            });

            if (myPostsButton) {
                myPostsButton.addEventListener('click', () => {
                    if (isLoading['my-posts'] && currentPage['my-posts'] > 1) return;
                    setActiveTab(myPostsButton);
                    loadPosts(myPostsButton.dataset.url, 'my-posts');
                });
                setActiveTab(myPostsButton);
                loadPosts(myPostsButton.dataset.url, 'my-posts');
            }

            if (votedPostsButton) {
                votedPostsButton.addEventListener('click', () => {
                    if (isLoading['voted-posts'] && currentPage['voted-posts'] > 1) return;
                    setActiveTab(votedPostsButton);
                    loadPosts(votedPostsButton.dataset.url, 'voted-posts');
                });
            }
        });
    </script>
@endpush

@push('schema')
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@graph": [
                {
                    "@@type": "Person",
                    "name": "{{ addslashes($displayName) }}",
            "alternateName": "{{ '@' . $user->username }}",
            "url": "{{ route('profile.show', ['username' => $user->username]) }}",
        @if($profilePic && !Str::contains($profilePic, 'default-pfp.png'))
            "image": "{{ $profilePic }}",
        @endif
        "description": "{{ addslashes(__('messages.profile.meta_description', ['username' => $user->username])) }}",
            "mainEntityOfPage": {
                "@@type": "ProfilePage",
                "@@id": "{{ route('profile.show', ['username' => $user->username]) }}"
            },
            "interactionStatistic": [
                {
                    "@@type": "InteractionCounter",
                    "interactionType": { "@@type": "WriteAction" },
                    "userInteractionCount": {{ $user->posts_count }}
        },
        {
            "@@type": "InteractionCounter",
            "interactionType": { "@@type": "LikeAction" },
            "userInteractionCount": {{ $totalVotesOnUserPosts }}
        }
    ]
        @if(!empty($user->external_links) && count(array_filter($user->external_links)) > 0)
            ,"sameAs": [
            @foreach(array_filter($user->external_links) as $index => $link_url)
                "{{ $link_url }}"{{ !$loop->last ? ',' : '' }}
            @endforeach
            ]
        @endif
        },
        {
            "@@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@@type": "ListItem",
                    "position": 1,
                    "name": "Home",
                    "item": "{{ route('home') }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 2,
                    "name": "{{ '@' . $user->username }}"
                }
            ]
        }
    ]
}
    </script>
@endpush
