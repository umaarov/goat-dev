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
                            <div class="flex items-center">
                                <h1 class="text-2xl font-semibold {{ $hasBackground ? 'text-white' : 'text-gray-800' }}" style="font-size: 1.5rem; line-height: 2rem; font-weight: 600; display: inline;">{{ $displayName }}</h1>
                                @if($isVerified)
                                    <span class="ml-1.5" title="{{ __('messages.profile.verified_account') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    </span>
                                @endif
                            </div>

                            @if($showSubUsername)
                                <p class="{{ $hasBackground ? 'text-gray-300' : 'text-gray-600' }} text-sm mt-0.5">{{ "@".$user->username }}</p>
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
        <div class="flex">
            <button id="load-my-posts" data-url="{{ route('profile.posts.data', $user->username) }}"
                    class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                {{ $isOwnProfile ? __('messages.profile.my_posts_tab') : __('messages.profile.users_posts_tab', ['username' => $user->username]) }}
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
<style>
    .comments-section {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease-in-out;
        opacity: 0;
    }

    .comments-section.active {
        max-height: 5000px;
        opacity: 1;
    }

    .comment-form-container {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .comments-section.active .comment-form-container {
        opacity: 1;
        transform: translateY(0);
    }

    .comments-list {
        transition: opacity 0.3s ease;
        min-height: 50px;
    }

    .comment {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.8s ease;
        padding: 8px 0;
    }

    .comment.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .comments-list .comment {
        padding-bottom: 12px;
    }

    .comments-list .comment:last-child {
        /*border-bottom: none;*/
        padding-bottom: 0;
        /*margin-bottom: 0;*/
    }

    .comment.highlighted-comment {
        background-color: rgba(59, 130, 246, 0.1);
        border-radius: 8px;
    }

    .replies-container {
        margin-left: calc(2rem + 0.75rem);
        margin-top: 8px;
        transition: max-height 0.4s ease, opacity 0.3s ease;
        max-height: 5000px;
        overflow: hidden;
    }

    .replies-container.hidden {
        max-height: 0;
        margin-top: 0;
        opacity: 0;
    }
    .replies-container .comment {
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .replies-container .comment:last-child {
        padding-bottom: 0;
    }

    .view-replies-button {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        margin-top: 1px;
    }

    .view-replies-button .line {
        height: 1px;
        width: 2rem;
        background-color: #d1d5db;
        margin-right: 0.5rem;
        transition: width 0.3s ease;
    }

    .view-replies-button:hover .line {
        background-color: #6b7280;
    }

    .view-replies-button.active .line {
        width: 1rem;
    }

    .replies-container .comment:last-child {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 0;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 4px;
        margin-top: 16px;
        transition: opacity 0.3s ease;
    }

    .pagination .page-item {
        margin: 0;
    }

    .pagination .page-link {
        display: inline-block;
        padding: 5px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        color: #4a5568;
        text-decoration: none;
        transition: background-color 0.2s ease;
        cursor: pointer;
    }

    .pagination .page-link:hover {
        background-color: #edf2f7;
    }

    .pagination .page-item.active .page-link {
        background-color: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    .pagination .page-item.disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .pagination .page-item.disabled .page-link {
        pointer-events: none;
    }

    .comments-list {
        transition: opacity 0.3s ease;
        min-height: 50px;
    }

    #comments-loading {
        opacity: 0;
        animation: fade-in 0.3s ease forwards;
    }

    .shimmer-comment {
        user-select: none;
        pointer-events: none;
    }
    .shimmer-bg {
        animation: shimmer 1.5s linear infinite;
        background-image: linear-gradient(to right, #e2e8f0 0%, #f8fafc 50%, #e2e8f0 100%);
        background-size: 200% 100%;
        background-color: #e2e8f0;
    }
    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    @keyframes fade-in {
        0% {
            opacity: 0;
        }
        100% {
            opacity: 1;
        }
    }

    .highlight-post {
        animation: micro-lift 0.8s ease-out;
    }

    @keyframes micro-lift {
        0% {
            transform: translateY(1px);
        }
        100% {
            transform: translateY(0);
        }
    }

    .zoomable-image {
        cursor: pointer;
    }

    .button-text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }

    .vote-button.voting-in-progress {
        opacity: 0.7;
        cursor: wait;
    }

    .vote-button.voting-in-progress .button-text-truncate::after {
        content: ' ...';
        display: inline-block;
        animation: dot-dot-dot 1s infinite;
    }

    .like-comment-button.processing-like {
        cursor: wait;
        opacity: 0.6;
    }

    @keyframes dot-dot-dot {
        0%, 80%, 100% {
            opacity: 0;
        }
        40% {
            opacity: 1;
        }
    }

    @-webkit-keyframes dot-dot-dot {
        0%, 80%, 100% {
            opacity: 0;
        }
        40% {
            opacity: 1;
        }
    }

    .shimmer-bg {
        animation: shimmer 1.5s linear infinite;
        background-image: linear-gradient(to right, #e2e8f0 0%, #f8fafc 50%, #e2e8f0 100%);
        background-size: 200% 100%;
        background-color: #e2e8f0;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }
</style>

@push('scripts')
    <script>
        window._currentUserId = {{ Auth::id() ?? 'null' }};
        const currentUserId = window._currentUserId;

        window.isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};
        window.profileUsername = '{{ $user->username }}';

        window.i18n = {
            profile: {
                js: {!! json_encode([
            'link_copied' => __('messages.profile.js.link_copied'),
            'time' => [
                'just_now' => __('messages.profile.js.time.just_now'),
                'minute'   => __('messages.profile.js.time.minute'),
                'minutes'  => __('messages.profile.js.time.minutes'),
                'hour'     => __('messages.profile.js.time.hour'),
                'hours'    => __('messages.profile.js.time.hours'),
                'day'      => __('messages.profile.js.time.day'),
                'days'     => __('messages.profile.js.time.days'),
                'ago'      => __('messages.profile.js.time.ago'),
            ],
            'no_comments'               => __('messages.profile.js.no_comments'),
            'no_comments_alt'           => __('messages.no_comments_yet'),
            'be_first_to_comment'       => __('messages.profile.js.be_first_to_comment'),
            'failed_load_comments'      => __('messages.profile.js.failed_load_comments'),
            'login_to_see_posts'        => __('messages.profile.js.login_to_see_posts'),
            'already_voted'             => __('messages.error_already_voted'),
            'vote_failed_http'          => __('messages.profile.js.vote_failed_http'),
            'vote_failed_connection'    => __('messages.profile.js.vote_failed_connection'),
            'confirm_delete_comment_title' => __('messages.profile.js.confirm_delete_comment_title'),
            'confirm_delete_comment_text'  => __('messages.profile.js.confirm_delete_comment_text'),
            'comment_empty'             => __('messages.profile.js.comment_empty'),
            'comment_button'            => __('messages.submit_comment_button'),
            'comment_button_submitting' => __('messages.profile.js.comment_button_submitting'),
            'error_prefix'              => __('messages.profile.js.error_prefix'),
            'delete_comment_title'      => __('messages.profile.js.delete_comment_title'),
            'loading'                   => __('messages.profile.js.loading'),
            'no_posts_found'            => __('messages.app.no_posts_found'),
            'load_more'                 => __('messages.profile.js.load_more'),
            'error_loading_posts'       => __('messages.profile.js.error_loading_posts'),
            'verified_account'          => __('messages.profile.verified_account'),
            'alt_profile_picture_js'    => __('messages.profile.alt_profile_picture_js'),
            'delete_comment_button_title' => __('messages.delete_button'),
        ]) !!}
            },
            pagination: {!! json_encode([
        'previous' => __('messages.pagination.previous'),
        'next'     => __('messages.pagination.next'),
    ]) !!}
        };

        window.i18n.profile.js.user_profile_link_template = "{{ route('profile.show', ['username' => ':USERNAME_PLACEHOLDER']) }}".replace(':USERNAME_PLACEHOLDER', ':username');

        document.addEventListener('DOMContentLoaded', function () {
            const shimmerTemplate = document.getElementById('shimmer-template')?.innerHTML || '';
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

        function scrollToPost(postId) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            setTimeout(() => {
                window.scrollTo({
                    top: postElement.offsetTop - 100,
                    behavior: 'smooth'
                });

                postElement.classList.add('highlight-post');
                setTimeout(() => {
                    postElement.classList.remove('highlight-post');
                }, 1500);
            }, 300);
        }


        function sharePost(postId) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            const questionElement = postElement.querySelector('.pt-4.px-4.font-semibold.text-center p');
            const question = questionElement ? questionElement.textContent : 'Check out this post';
            const slug = question.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-')
                .substring(0, 60);

            const shareUrl = `${window.location.origin}/p/${postId}/${slug}`;

            if (navigator.share) {
                navigator.share({
                    title: question,
                    url: shareUrl
                }).catch(error => {
                    console.log('Error sharing:', error);
                    fallbackShare(shareUrl);
                });
            } else {
                fallbackShare(shareUrl);
            }

            updateShareCount(postId);
        }

        function fallbackShare(url) {
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);

            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);

            if (window.showToast) {
                window.showToast(window.i18n.profile.js.link_copied);
            } else {
                alert(window.i18n.profile.js.link_copied);
            }
        }


        function updateShareCount(postId) {
            const shareCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:last-child span`);
            if (shareCountElement) {
                const currentCount = parseInt(shareCountElement.textContent);
                const newCount = isNaN(currentCount) ? 1 : currentCount + 1;
                shareCountElement.textContent = newCount;

                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                fetch(`/posts/${postId}/share`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                }).catch(error => {
                    console.error('Error updating share count:', error);
                });
            }
        }

        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            const i18nTime = window.i18n.profile.js.time;

            if (diffInSeconds < 60) {
                return i18nTime.just_now;
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} ${minutes === 1 ? i18nTime.minute : i18nTime.minutes} ${i18nTime.ago}`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} ${hours === 1 ? i18nTime.hour : i18nTime.hours} ${i18nTime.ago}`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} ${days === 1 ? i18nTime.day : i18nTime.days} ${i18nTime.ago}`;
            } else {
                return date.toLocaleDateString(document.documentElement.lang || 'en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function openImageModal(event) {
            // event.stopPropagation();
            console.log('openImageModal triggered by:', event.target);
            const fullSrc = event.target.dataset.fullSrc || event.target.src;
            const altText = event.target.alt || (window.i18n.app && window.i18n.app.js && window.i18n.app.js.image_viewer_alt ? window.i18n.app.js.image_viewer_alt : 'View image'); // Added safe access for i18n

            const existingModal = document.getElementById('image-viewer-modal');
            if (existingModal) existingModal.remove();

            const modal = document.createElement('div');
            modal.id = 'image-viewer-modal';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.85)';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.zIndex = '10000';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'image-viewer-caption');

            const imgElement = document.createElement('img');
            imgElement.src = fullSrc;
            imgElement.alt = altText;
            imgElement.style.maxWidth = '90%';
            imgElement.style.maxHeight = '90%';
            imgElement.style.objectFit = 'contain';

            const closeButton = document.createElement('button');
            closeButton.innerHTML = '&times;';
            const closeTitle = (window.i18n.app && window.i18n.app.js && window.i18n.app.js.image_viewer_close_title ? window.i18n.app.js.image_viewer_close_title : 'Close'); // Safe access
            closeButton.title = closeTitle;
            closeButton.setAttribute('aria-label', closeTitle);
            closeButton.style.position = 'absolute';
            closeButton.style.top = '15px';
            closeButton.style.right = '25px';
            closeButton.style.fontSize = '2.5rem';
            closeButton.style.color = 'white';
            closeButton.style.background = 'transparent';
            closeButton.style.border = 'none';
            closeButton.style.cursor = 'pointer';

            const caption = document.createElement('div');
            caption.id = 'image-viewer-caption';
            caption.textContent = altText;
            caption.style.color = 'white';
            caption.style.textAlign = 'center';
            caption.style.position = 'absolute';
            caption.style.bottom = '20px';
            caption.style.width = '100%';
            caption.classList.add('sr-only');

            modal.appendChild(imgElement);
            modal.appendChild(closeButton);
            modal.appendChild(caption);
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';

            const closeModal = () => {
                modal.remove();
                document.body.style.overflow = '';
                document.removeEventListener('keydown', escKeyHandler);
            };

            const escKeyHandler = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                }
            };

            closeButton.onclick = closeModal;
            modal.onclick = (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            };
            document.addEventListener('keydown', escKeyHandler);
            imgElement.focus();
        }

        function initializeZoomableImages(parentElement = document) {
            console.log('Initializing zoomable images in:', parentElement);
            parentElement.querySelectorAll('.zoomable-image').forEach(image => {
                image.removeEventListener('click', openImageModal);
                image.addEventListener('click', openImageModal);
            });
        }

        // initializeZoomableImages();


        function initializePostInteractions() {
            const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
            commentsSections.forEach(section => {
                if (!section.classList.contains('comments-section')) {
                    section.classList.add('comments-section');
                }
                if (!section.classList.contains('active')) {
                    section.classList.add('hidden');
                }
                const formContainer = section.querySelector('form')?.closest('div');
                if (formContainer && !formContainer.classList.contains('comment-form-container')) {
                    formContainer.classList.add('comment-form-container');
                }
            });

            document.querySelectorAll('[data-action="share-post"]').forEach(button => {
                const postId = button.dataset.postId;
                if (!button.hasAttribute('data-share-initialized')) {
                    button.onclick = () => sharePost(postId);
                    button.setAttribute('data-share-initialized', 'true');
                }
            });

            document.querySelectorAll('[data-action="toggle-comments"]').forEach(button => {
                const postId = button.dataset.postId;
                if (!button.hasAttribute('data-toggle-initialized')) {
                    button.onclick = () => toggleComments(postId);
                    button.setAttribute('data-toggle-initialized', 'true');
                }
            });

            document.querySelectorAll('button.vote-button[data-post-id][data-option]').forEach(button => {
                const postId = button.dataset.postId;
                const option = button.dataset.option;
                if (!button.hasAttribute('data-vote-initialized')) {
                    button.onclick = () => voteForOption(postId, option);
                    button.setAttribute('data-vote-initialized', 'true');
                }
            });

            document.querySelectorAll('form[data-action="submit-comment"]').forEach(form => {
                const postId = form.dataset.postId;
                if (!form.hasAttribute('data-submit-initialized')) {
                    form.onsubmit = (event) => submitComment(postId, event);
                    form.setAttribute('data-submit-initialized', 'true');
                }
            });
            // initializeZoomableImages(document.getElementById('posts-container'));
        }


        let currentlyOpenCommentsId = null;

        function toggleComments(postId) {
            const clickedCommentsSection = document.getElementById(`comments-section-${postId}`);
            if (!clickedCommentsSection) return;
            const isActive = clickedCommentsSection.classList.contains('active');

            if (window.currentlyOpenCommentsId && window.currentlyOpenCommentsId !== postId) {
                const previousCommentsSection = document.getElementById(`comments-section-${window.currentlyOpenCommentsId}`);
                if (previousCommentsSection) {
                    previousCommentsSection.classList.remove('active');
                    setTimeout(() => {
                        previousCommentsSection.classList.add('hidden');
                    }, 500);
                }
            }

            if (isActive) {
                clickedCommentsSection.classList.remove('active');
                window.currentlyOpenCommentsId = null;
                setTimeout(() => {
                    clickedCommentsSection.classList.add('hidden');
                }, 500);
            } else {
                clickedCommentsSection.classList.remove('hidden');
                setTimeout(() => {
                    clickedCommentsSection.classList.add('active');
                    window.currentlyOpenCommentsId = postId;
                    if (!clickedCommentsSection.dataset.loaded) {
                        loadComments(postId, 1);
                    }
                }, 10);
            }
        }

        function getCommentShimmerHTML() {
            const template = document.getElementById('comment-shimmer-template');
            if (!template) {
                console.warn("Shimmer template not found. Using fallback.");
                return '<div class="text-center py-4 text-sm text-gray-500">Loading comments...</div>';
            }
            const shimmerContent = template.innerHTML;
            return shimmerContent.repeat(3);
        }

        function loadComments(postId, page) {
            const commentsSection = document.getElementById(`comments-section-${postId}`);
            const commentsContainer = commentsSection.querySelector('.comments-list');
            if (!commentsContainer) {
                console.error('Comments container not found');
                return;
            }
            const isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};

            if (!isLoggedIn) {
                commentsContainer.innerHTML = `<div class="text-center py-4"><p class="text-sm text-gray-500">${window.translations.js_login_to_comment}</p></div>`;
                const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                if (paginationContainer) {
                    paginationContainer.innerHTML = '';
                }
                commentsSection.dataset.loaded = "true";
                return;
            }

            commentsContainer.innerHTML = getCommentShimmerHTML();

            fetch(`/posts/${postId}/comments?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    commentsContainer.innerHTML = '';

                    if (data.comments.data.length === 0 && page === 1) {
                        commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.translations.js_no_comments_be_first}</p>`;
                        return;
                    }

                    const fragment = document.createDocumentFragment();
                    data.comments.data.forEach(comment => {
                        const commentDiv = createCommentElement(comment, postId, false);
                        fragment.appendChild(commentDiv);

                    });
                    commentsContainer.appendChild(fragment);

                    animateComments(commentsContainer);
                    const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                    if (paginationContainer) {
                        renderPagination(data.comments, postId, paginationContainer);
                    }
                    commentsSection.dataset.loaded = "true";
                    commentsSection.dataset.currentPage = page;
                })
                .catch(error => {
                    console.error('Error:', error);
                    commentsContainer.innerHTML = `<p class="text-red-500 text-center">${window.translations.js_failed_load_comments}</p>`;
                });
        }

        async function voteForOption(postId, option) {
            if (!window.isLoggedIn) {
                if (window.showToast) window.showToast(window.i18n.profile.js.login_to_vote, 'warning');
                return;
            }

            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) {
                console.error(`Post element with ID post-${postId} not found.`);
                return;
            }

            const clickedButton = postElement.querySelector(`button.vote-button[data-option="${option}"]`);
            const otherButtonOption = option === 'option_one' ? 'option_two' : 'option_one';
            const otherButton = postElement.querySelector(`button.vote-button[data-option="${otherButtonOption}"]`);

            if (!clickedButton || !otherButton) {
                console.error('Vote buttons not found for post-' + postId);
                return;
            }

            if (clickedButton.disabled || clickedButton.classList.contains('voting-in-progress')) {
                return;
            }

            const knownUserVote = postElement.dataset.userVote;
            if (knownUserVote && (knownUserVote === 'option_one' || knownUserVote === 'option_two')) {
                if (knownUserVote === option) {
                    if (window.showToast) window.showToast(window.i18n.profile.js.already_voted, 'info');
                    const currentVoteData = {
                        user_vote: knownUserVote,
                        option_one_votes: parseInt(postElement.dataset.optionOneVotes, 10),
                        option_two_votes: parseInt(postElement.dataset.optionTwoVotes, 10),
                        total_votes: parseInt(postElement.dataset.optionOneVotes, 10) + parseInt(postElement.dataset.optionTwoVotes, 10),
                        message: window.i18n.profile.js.already_voted
                    };
                    updateVoteUI(postId, currentVoteData.user_vote, currentVoteData);
                    return;
                }
            }


            const originalOptionOneVotes = parseInt(postElement.dataset.optionOneVotes, 10) || 0;
            const originalOptionTwoVotes = parseInt(postElement.dataset.optionTwoVotes, 10) || 0;
            const originalUserVoteState = postElement.dataset.userVote || '';

            const originalClickedButtonClasses = Array.from(clickedButton.classList);
            const originalOtherButtonClasses = Array.from(otherButton.classList);

            const totalVotesDisplayElement = postElement.querySelector('.total-votes-display');
            const originalTotalVotesText = totalVotesDisplayElement ? totalVotesDisplayElement.textContent : (originalOptionOneVotes + originalOptionTwoVotes).toString();


            clickedButton.classList.add('voting-in-progress');
            clickedButton.disabled = true;
            otherButton.disabled = true;

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            let responseOk = false;

            try {
                const response = await fetch(`/posts/${postId}/vote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({option: option})
                });

                const responseData = await response.json();
                responseOk = response.ok;

                if (responseOk) { // HTTP 200-299
                    updateVoteUI(postId, responseData.user_vote, responseData);
                    if (window.showToast && responseData.message && !responseData.message.toLowerCase().includes('already voted')) {
                        window.showToast(responseData.message, 'success');
                    } else if (window.showToast && responseData.message && responseData.message.toLowerCase().includes('already voted')) {
                        window.showToast(responseData.message, 'info');
                    }
                } else { // HTTP errors (409, 422, 500, etc.)
                    const errorMessage = responseData.message || responseData.error || (responseData.errors ? Object.values(responseData.errors).join(', ') : `${window.i18n.profile.js.vote_failed_http} (HTTP ${response.status})`);
                    if (window.showToast) {
                        window.showToast(errorMessage, response.status === 409 ? 'info' : 'error');
                    }

                    if (response.status === 409 && responseData.user_vote && typeof responseData.option_one_votes !== 'undefined') {
                        updateVoteUI(postId, responseData.user_vote, responseData);
                    } else {
                        clickedButton.className = originalClickedButtonClasses.join(' ');
                        otherButton.className = originalOtherButtonClasses.join(' ');
                        postElement.dataset.userVote = originalUserVoteState;
                        postElement.dataset.optionOneVotes = originalOptionOneVotes.toString();
                        postElement.dataset.optionTwoVotes = originalOptionTwoVotes.toString();
                        if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;
                    }
                }
            } catch (error) {
                console.error('Error voting (catch):', error);
                if (window.showToast) {
                    window.showToast(window.i18n.profile.js.vote_failed_connection, 'error');
                }
                clickedButton.className = originalClickedButtonClasses.join(' ');
                otherButton.className = originalOtherButtonClasses.join(' ');
                postElement.dataset.userVote = originalUserVoteState;
                postElement.dataset.optionOneVotes = originalOptionOneVotes.toString();
                postElement.dataset.optionTwoVotes = originalOptionTwoVotes.toString();
                if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;

            } finally {
                clickedButton.classList.remove('voting-in-progress');
                clickedButton.disabled = false;
                otherButton.disabled = false;
            }
        }

        function updateVoteUI(postId, userVotedOption, voteData) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) {
                console.error(`Post element with ID post-${postId} not found.`);
                return;
            }

            postElement.dataset.userVote = userVotedOption || '';
            postElement.dataset.optionOneVotes = voteData.option_one_votes;
            postElement.dataset.optionTwoVotes = voteData.option_two_votes;

            const totalVotesDisplayElement = postElement.querySelector('.total-votes-display');
            if (totalVotesDisplayElement) {
                totalVotesDisplayElement.textContent = voteData.total_votes;
            }

            const optionOneButton = postElement.querySelector('button.vote-button[data-option="option_one"]');
            const optionTwoButton = postElement.querySelector('button.vote-button[data-option="option_two"]');

            if (optionOneButton && optionTwoButton) {
                const optionOneTitle = postElement.dataset.optionOneTitle || 'Option 1';
                const optionTwoTitle = postElement.dataset.optionTwoTitle || 'Option 2';

                const totalVotes = parseInt(voteData.total_votes, 10);
                const optionOneVotes = parseInt(voteData.option_one_votes, 10);
                const optionTwoVotes = parseInt(voteData.option_two_votes, 10);

                const percentOne = totalVotes > 0 ? Math.round((optionOneVotes / totalVotes) * 100) : 0;
                const percentTwo = totalVotes > 0 ? Math.round((optionTwoVotes / totalVotes) * 100) : 0;

                const optionOneTextElement = optionOneButton.querySelector('.button-text-truncate');
                if (optionOneTextElement) {
                    optionOneTextElement.textContent = `${optionOneTitle} (${percentOne}%)`;
                } else {
                    optionOneButton.textContent = `${optionOneTitle} (${percentOne}%)`;
                }

                const optionTwoTextElement = optionTwoButton.querySelector('.button-text-truncate');
                if (optionTwoTextElement) {
                    optionTwoTextElement.textContent = `${optionTwoTitle} (${percentTwo}%)`;
                } else {
                    optionTwoButton.textContent = `${optionTwoTitle} (${percentTwo}%)`;
                }


                const highlightClasses = ['bg-blue-800', 'text-white', 'border-blue-800'];
                const defaultClasses = ['bg-white', 'text-gray-700', 'border', 'border-gray-300', 'hover:bg-gray-50'];
                const nonVotedPeerClasses = ['bg-gray-100', 'text-gray-600', 'border', 'border-gray-300'];

                [optionOneButton, optionTwoButton].forEach(button => {
                    button.classList.remove(...highlightClasses, ...defaultClasses, ...nonVotedPeerClasses);
                    button.classList.remove('hover:bg-gray-50', 'hover:bg-blue-700');
                });


                if (userVotedOption) {
                    if (userVotedOption === 'option_one') {
                        optionOneButton.classList.add(...highlightClasses);
                        optionTwoButton.classList.add(...nonVotedPeerClasses);
                    } else if (userVotedOption === 'option_two') {
                        optionTwoButton.classList.add(...highlightClasses);
                        optionOneButton.classList.add(...nonVotedPeerClasses);
                    }
                    optionOneButton.dataset.tooltipShowCount = "true";
                    optionTwoButton.dataset.tooltipShowCount = "true";

                } else {
                    optionOneButton.classList.add(...defaultClasses);
                    optionTwoButton.classList.add(...defaultClasses);
                    optionOneButton.dataset.tooltipShowCount = "false";
                    optionTwoButton.dataset.tooltipShowCount = "false";
                }
            }
        }

        function deleteComment(commentId, event) {
            event.preventDefault();

            if (!confirm(window.i18n.profile.js.confirm_delete_comment_text)) {
                return;
            }

            const commentElement = document.getElementById('comment-' + commentId);
            if (!commentElement) {
                console.error('Comment element not found for deletion:', commentId);
                return;
            }

            const postId = commentElement.closest('[id^="comments-section-"]')?.id.split('-')[2];
            if (!postId) {
                console.error('Could not determine postId for comment deletion.');
                return;
            }

            commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            commentElement.style.opacity = '0';
            commentElement.style.transform = 'translateY(10px)';


            const url = `/comments/${commentId}`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                credentials: 'include'
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            throw new Error(errData.error || errData.message || `Server error: ${response.status}`);
                        }).catch(() => {
                            throw new Error(`Failed to delete comment. Server responded with ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    commentElement.addEventListener('transitionend', () => {
                        commentElement.remove();

                        const commentCountElement = document.querySelector(`#post-${postId} .comment-count-display`);
                        if (commentCountElement) {
                            const currentCount = parseInt(commentCountElement.textContent) || 0;
                            commentCountElement.textContent = Math.max(0, currentCount - 1);
                        }

                        const commentsSection = document.getElementById(`comments-section-${postId}`);
                        const commentsContainer = commentsSection?.querySelector('.comments-list');
                        if (commentsContainer && commentsContainer.children.length === 0) {
                            const currentPage = parseInt(commentsSection.dataset.currentPage) || 1;
                            if (currentPage > 1) {
                                commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.i18n.profile.js.no_comments_alt}</p>`;
                            } else {
                                commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.i18n.profile.js.no_comments_alt} ${window.i18n.profile.js.be_first_to_comment}</p>`;
                            }
                            const paginationContainer = commentsSection?.querySelector(`#pagination-container-${postId}`);
                            if (paginationContainer) paginationContainer.innerHTML = '';
                        }
                    });

                    setTimeout(() => {
                        if (commentElement.parentNode) {
                            commentElement.remove();
                        }
                    }, 350);

                    if (window.showToast && data.message) {
                        window.showToast(data.message, 'success');
                    }

                })
                .catch(error => {
                    console.error('Error deleting comment:', error);
                    commentElement.style.opacity = '1';
                    commentElement.style.transform = 'translateY(0)';
                    if (window.showToast) {
                        window.showToast(error.message || 'Failed to delete comment. Please try again.', 'error');
                    }
                });
        }

        function submitComment(postId, event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const contentInput = form.elements.content;
            const content = contentInput.value.trim();

            if (!content) {
                if (window.showToast) window.showToast(window.i18n.profile.js.comment_empty, 'warning');
                contentInput.focus();
                return;
            }

            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = `<div class="inline-block animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div> ${window.i18n.profile.js.comment_button_submitting}`;

            const url = `/posts/${postId}/comments`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({content: content})
            })
                .then(response => {
                    if (!response.ok) {
                        console.log('Response not OK. Status:', response.status);
                        return response.json()
                            .catch(jsonParseError => {
                                console.error('Failed to parse JSON from non-OK response:', jsonParseError);
                                throw new Error(`Server error: ${response.status}. Response body not valid JSON.`);
                            })
                            .then(errData => {
                                console.log('Parsed error data from server (errData):', errData);
                                let specificMessage = `Failed to submit comment (Server error: ${response.status}).`;

                                if (errData && errData.errors && errData.errors.content && Array.isArray(errData.errors.content) && errData.errors.content.length > 0) {
                                    specificMessage = errData.errors.content.join(' ');
                                } else if (errData && errData.message) {
                                    specificMessage = errData.message;
                                }
                                console.log('Throwing error with specific message:', specificMessage);
                                throw new Error(specificMessage);
                            });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.comment) {
                        contentInput.value = '';

                        const commentsSection = document.getElementById(`comments-section-${postId}`);
                        const commentsContainer = commentsSection?.querySelector('.comments-list');

                        if (commentsContainer) {
                            const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                            if (noCommentsMessage && (noCommentsMessage.textContent.includes(window.i18n.profile.js.no_comments_alt) || noCommentsMessage.textContent.includes("No comments yet"))) {
                                commentsContainer.innerHTML = '';
                            }

                            const commentElement = createCommentElement(data.comment, postId);
                            commentsContainer.insertBefore(commentElement, commentsContainer.firstChild);

                            setTimeout(() => commentElement.classList.add('visible'), 10);

                            const commentCountElement = document.querySelector(`#post-${postId} .comment-count-display`);
                            if (commentCountElement) {
                                const currentCount = parseInt(commentCountElement.textContent) || 0;
                                commentCountElement.textContent = currentCount + 1;
                            }
                            // initializeZoomableImages(commentElement);
                        }

                        if (window.showToast && data.message) {
                            window.showToast(data.message, 'success');
                        } else if (window.showToast) {
                            window.showToast('Comment posted!', 'success');
                        }

                    } else {
                        if (data.errors) {
                            const errorMessages = Object.values(data.errors).flat().join(' ');
                            if (window.showToast) window.showToast(`${window.i18n.profile.js.error_prefix} ${errorMessages}`, 'error');
                        } else {
                            if (window.showToast) window.showToast(data.message || 'Failed to add comment. Unexpected response format after success.', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Caught in final .catch for submitComment:', error);
                    if (window.showToast) {
                        window.showToast(error.message || 'Failed to add comment. Please try again.', 'error');
                    } else {
                        alert(error.message || 'Failed to add comment. Please try again.');
                    }
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
        }


        function linkifyContent(text) {
            if (typeof text !== 'string') return '';
            const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
            const mentionRegex = /@([a-zA-Z0-9_]+)/g;

            let linkedText = text.replace(urlRegex, function(url, p1, p2, p3) {
                const fullUrl = p3 ? 'http://' + p3 : p1;
                return `<a href="${fullUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all">${url}</a>`;
            });

            linkedText = linkedText.replace(mentionRegex, function(match, username) {
                return `<a href="/@${username}" class="text-blue-600 hover:underline font-medium">@${username}</a>`;
            });

            return linkedText;
        }

        function createCommentElement(commentData, postId, isReply = false) {
            const commentDiv = document.createElement('div');
            // commentDiv.className = 'comment py-3 border-b border-gray-200';
            commentDiv.className = 'comment';

            // if (!isReply) {
            //     commentDiv.classList.add('border-b', 'border-gray-200');
            // }

            if (!commentData.id.toString().startsWith('temp-')) {
                commentDiv.id = 'comment-' + commentData.id;
            }

            const profilePic = commentData.user.profile_picture
                ? (commentData.user.profile_picture.startsWith('http') ? commentData.user.profile_picture : '/storage/' + commentData.user.profile_picture)
                : '/images/default-pfp.png';

            const altProfilePic = (window.translations.profile_alt_picture || 'Profile picture of :username').replace(':username', commentData.user.username);

            // const isVerified = ['goat', 'umarov'].includes(commentData.user.username);
            // const verifiedIconHTML = isVerified ? `<span class="ml-1 self-center" title="${window.translations.verified_account || 'Verified Account'}"><svg class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>` : '';

            const username = commentData.user ? commentData.user.username : null;
            let userProfileLinkHTML = `<span class="font-semibold text-gray-900">Unknown User</span>`;

            if (username && typeof username === 'string' && !username.includes('${') && !username.includes(':')) {
                const isVerified = ['goat', 'umarov'].includes(username);
                const verifiedIconHTML = isVerified ? `<span class="ml-1 self-center" title="${window.i18n.profile.js.verified_account || 'Verified Account'}"><svg class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>` : '';
                userProfileLinkHTML = `<a href="/@${username}" class="font-semibold text-gray-900 hover:underline">${username}</a>${verifiedIconHTML}`;
            } else {
                console.warn('Invalid or placeholder username detected in comment data:', commentData);
            }

            const likesCount = commentData.likes_count || 0;
            const isLiked = commentData.is_liked_by_current_user || false;

            const filledHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />';
            const outlineHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656zM10 15.93l5.828-5.828a2 2 0 10-2.828-2.828L10 10.274l-2.828-2.829a2 2 0 00-2.828 2.828L10 15.93z" clip-rule="evenodd" />';

            const likeButtonHTML = `
            <div class="flex items-center text-xs">
                <button onclick="toggleCommentLike(${commentData.id}, this)"
                        class="like-comment-button flex items-center mb-0.5 rounded-full transition-all duration-150 ease-in-out group ${isLiked ? 'text-red-500 hover:bg-red-100' : 'text-gray-400 hover:text-red-500 hover:bg-red-50'}"
                        data-comment-id="${commentData.id}"
                        title="${isLiked ? (window.translations.unlike_comment_title || 'Unlike') : (window.translations.like_comment_title || 'Like')}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 comment-like-icon group-hover:scale-125 transition-transform duration-150" viewBox="0 0 20 20" fill="currentColor">
                        ${isLiked ? filledHeartSVG : outlineHeartSVG}
                    </svg>
                </button>
                <span class="ml-0.5 comment-likes-count font-medium tabular-nums ${parseInt(likesCount) === 0 ? 'text-gray-400' : 'text-gray-700'}" id="comment-likes-count-${commentData.id}">
                    ${likesCount}
                </span>
            </div>
        `;
            let replyToHTML = '';
            // if (commentData.parent_id && commentData.parent && commentData.parent.user) {
            //     const parentUsername = commentData.parent.user.username;
            //     if (!commentData.content.includes(`@${parentUsername}`)) {
            //         replyToHTML = `<a href="/@${parentUsername}" class="text-blue-600 hover:underline mr-1">@${parentUsername}</a>`;
            //     }
            // }
            // const isNestedReply = commentData.parent && commentData.parent.parent_id !== null;
            // if (isNestedReply) {
            //     if (commentData.parent.user) {
            //         const parentUsername = commentData.parent.user.username;
            //         if (!commentData.content.includes(`@${parentUsername}`)) {
            //             replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            //         }
            //     }
            // }

            // if (isReply) {
            //     console.log('%c DEBUG: Checking reply logic... ', 'background: #f2f2f2; color: #333;', {
            //         parent_id: commentData.parent_id,
            //         root_comment_id: commentData.root_comment_id,
            //         parent_id_type: typeof commentData.parent_id,
            //         root_comment_id_type: typeof commentData.root_comment_id,
            //     });
            //
            //     const isNestedReply = commentData.parent_id &&
            //         commentData.root_comment_id &&
            //         Number(commentData.parent_id) !== Number(commentData.root_comment_id);
            //
            //     console.log(`%c DEBUG: Is this a nested reply? -> ${isNestedReply}`, 'font-weight: bold;');
            //
            //     if (isNestedReply) {
            //         if (commentData.parent && commentData.parent.user) {
            //             const parentUsername = commentData.parent.user.username;
            //             if (!commentData.content.includes(`@${parentUsername}`)) {
            //                 console.log(`%c DEBUG: Decision: ADDING @mention for nested reply.`, 'color: green');
            //                 replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            //             }
            //         }
            //     } else {
            //         console.log(`%c DEBUG: Decision: NOT adding @mention.`, 'color: orange');
            //     }
            // }

            const isNestedReply = commentData.parent_id &&
                commentData.root_comment_id &&
                Number(commentData.parent_id) !== Number(commentData.root_comment_id);

            if (isNestedReply) {
                if (commentData.parent && commentData.parent.user) {
                    const parentUsername = commentData.parent.user.username;
                    if (!commentData.content.includes(`@${parentUsername}`)) {
                        replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
                    }
                }
            }

            // if (isReply && commentData.parent && commentData.parent.user && commentData.parent_id !== commentData.root_comment_id) {
            //     const parentUsername = commentData.parent.user.username;
            //     if (!commentData.content.includes(`@${parentUsername}`)) {
            //         replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            //     }
            // }

            const linkedCommentContent = linkifyContent(commentData.content);

            let repliesActionsHTML = '';

            let goToParentArrowHTML = '';
            if (isReply && Number(commentData.parent_id) !== Number(commentData.root_comment_id)) {
                goToParentArrowHTML = `<button onclick="scrollToComment('comment-${commentData.parent_id}')" class="p-1 rounded-full hover:bg-gray-200" title="${window.translations.go_to_parent_comment_title || 'Go to parent comment'}"><svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V5.612L5.28 9.68a.75.75 0 01-1.06-1.06l5.25-5.25a.75.75 0 011.06 0l5.25 5.25a.75.75 0 11-1.06 1.06L10.75 5.612V16.25A.75.75 0 0110 17z" clip-rule="evenodd" /></svg></button>`;
            }

            let repliesToggleHTML = '';
            if (!isReply && commentData.flat_replies && commentData.flat_replies.length > 0) {
                const replyCount = commentData.flat_replies.length;
                const viewText = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', replyCount);
                repliesToggleHTML = `<button class="view-replies-button font-semibold hover:underline" onclick="toggleRepliesContainer(this, 'comment-${commentData.id}')">${viewText}</button>`;
            }
            const replyButton = `<button onclick="prepareReply('${postId}', '${commentData.id}', '${commentData.user.username}')" class="font-semibold hover:underline" title="Reply to ${commentData.user.username}">${window.translations.reply_button_text || 'Reply'}</button>`;

            commentDiv.innerHTML = `
            <div class="flex items-start space-x-3">
                <img src="${profilePic}" alt="${altProfilePic}" loading="lazy" decoding="async" class="w-8 h-8 rounded-full flex-shrink-0 cursor-pointer zoomable-image" data-full-src="${profilePic}">
                <div class="flex-1">
                    <div class="text-sm">
                        <div class="flex items-center">
                            ${userProfileLinkHTML}
                        </div>
                        <span class="text-gray-800">${replyToHTML} ${linkedCommentContent}</span>
                    </div>
                    <div class="comment-actions mt-1.5 flex items-center space-x-3 text-xs text-gray-500">
                        <small class="text-xs text-gray-500" title="${commentData.created_at}">${formatTimestamp(commentData.created_at)}</small>
                        ${ {{ Auth::check() ? 'true' : 'false' }} ? `<div class="flex items-center">${likeButtonHTML}</div>` : ''}
                        ${replyButton}
                        ${repliesActionsHTML}
                        ${goToParentArrowHTML}
                    </div>
                </div>
${canDeleteComment(commentData) ? `
                <div class="ml-2 pl-1 flex-shrink-0">
                    <form onsubmit="deleteComment('${commentData.id}', event)" class="inline">
                        <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors duration-150 ease-in-out text-xs p-1 mt-0.5" title="${window.translations.delete_comment_title || 'Delete comment'}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </form>
                </div>` : ''}
            </div>
            <div class="replies-container hidden"></div>
            `;
            const actionsContainer = commentDiv.querySelector('.comment-actions');
            const repliesContainer = commentDiv.querySelector('.replies-container');

            if (!isReply) {
                const loadedRepliesCount = commentData.flat_replies ? commentData.flat_replies.length : 0;
                const totalRepliesCount = commentData.replies_count || 0;
                const hasMoreReplies = totalRepliesCount > loadedRepliesCount;

                if (totalRepliesCount > 0) {
                    const toggleButton = document.createElement('button');
                    toggleButton.className = 'view-replies-button font-semibold hover:underline';
                    toggleButton.textContent = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', totalRepliesCount);
                    toggleButton.onclick = () => toggleRepliesContainer(toggleButton, 'comment-' + commentData.id);
                    actionsContainer.appendChild(toggleButton);
                }

                if (loadedRepliesCount > 0) {
                    commentData.flat_replies.forEach(replyData => {
                        const replyElement = createCommentElement(replyData, postId, true);
                        repliesContainer.appendChild(replyElement);
                    });
                }

                if (hasMoreReplies) {
                    const remainingCount = totalRepliesCount - loadedRepliesCount;
                    const loadMoreWrapper = document.createElement('div');
                    loadMoreWrapper.className = 'load-more-replies-wrapper mt-2'; // Added margin
                    loadMoreWrapper.innerHTML = `<button class="text-xs font-semibold text-blue-600 hover:underline" onclick="loadMoreReplies(this, ${commentData.id})">${(window.translations.view_more_replies_text || 'View :count more replies').replace(':count', remainingCount)}</button>`;
                    repliesContainer.appendChild(loadMoreWrapper);
                }
            }

            return commentDiv;
        }

        function scrollToComment(commentId) {
            const element = document.getElementById(commentId);

            if (!element) {
                if (window.showToast) {
                    const message = window.translations.js_parent_comment_deleted || 'The comment you are replying to has been deleted.';
                    window.showToast(message, 'warning');
                } else {
                    console.warn(`Could not find comment ${commentId} to scroll to.`);
                }
                return;
            }

            const repliesContainer = element.closest('.replies-container');
            if (repliesContainer && repliesContainer.classList.contains('hidden')) {
                const rootComment = repliesContainer.closest('.comment');
                const toggleButton = rootComment.querySelector('.view-replies-button');
                if (toggleButton) {
                    toggleButton.click();
                }
            }

            setTimeout(() => {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                element.classList.add('highlighted-comment');
                setTimeout(() => {
                    element.classList.remove('highlighted-comment');
                }, 1500);
            }, 100);
        }

        function toggleRepliesContainer(button, commentId) {
            const commentElement = document.getElementById(commentId);
            if (!commentElement) return;

            const repliesContainer = commentElement.querySelector('.replies-container');
            if (!repliesContainer) return;

            const loadMoreWrapper = commentElement.querySelector('.load-more-replies-wrapper');
            const isHidden = repliesContainer.classList.contains('hidden');

            if (isHidden) {
                repliesContainer.classList.remove('hidden');

                setTimeout(() => {
                    animateComments(repliesContainer);
                }, 50);

                if (loadMoreWrapper) {
                    loadMoreWrapper.classList.remove('hidden');
                }

                button.classList.add('active');
                button.textContent = window.translations.hide_replies_text || 'Hide replies';

            } else {
                repliesContainer.classList.add('hidden');
                button.classList.remove('active');

                const existingRepliesCount = repliesContainer.querySelectorAll('.comment').length;
                const loadMoreButton = repliesContainer.querySelector('.load-more-replies-wrapper button');
                let totalCount = existingRepliesCount;

                if (loadMoreButton) {
                    const matches = loadMoreButton.textContent.match(/\d+/);
                    if (matches) {
                        const remainingCount = parseInt(matches[0], 10);
                        totalCount = existingRepliesCount + remainingCount;
                    }
                }
                button.textContent = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', totalCount);
            }
        }

        async function loadMoreReplies(button, rootCommentId) {
            const commentElement = document.getElementById(`comment-${rootCommentId}`);
            if (!commentElement) return;

            const repliesContainer = commentElement.querySelector('.replies-container');
            const existingReplyIds = Array.from(repliesContainer.querySelectorAll('.comment[id^="comment-"]')).map(el => el.id.replace('comment-', ''));

            button.disabled = true;
            button.textContent = 'Loading...';
            const loadMoreWrapper = button.parentElement;

            const excludeParams = existingReplyIds.map(id => `exclude_ids[]=${id}`).join('&');
            const url = `/comments/${rootCommentId}/replies?${excludeParams}`;

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error('Network response was not ok.');
                const paginatedReplies = await response.json();

                const fragment = document.createDocumentFragment();
                paginatedReplies.data.forEach(reply => {
                    const replyDiv = createCommentElement(reply, reply.post_id, true);
                    fragment.appendChild(replyDiv);
                });
                repliesContainer.insertBefore(fragment, loadMoreWrapper);

                animateComments(repliesContainer);

                const totalLoaded = existingReplyIds.length + paginatedReplies.data.length;
                const totalAvailable = paginatedReplies.total;
                if (totalLoaded >= totalAvailable) {
                    loadMoreWrapper.remove();
                } else {
                    const remaining = totalAvailable - totalLoaded;
                    button.textContent = (window.translations.view_more_replies_text || 'View :count more replies').replace(':count', remaining);
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Error loading more replies:', error);
                button.textContent = 'Error. Click to retry.';
                button.disabled = false;
            }
        }


        function prepareReply(postId, commentId, username) {
            const form = document.getElementById(`comment-form-${postId}`);
            if (!form) return;

            const targetCommentElement = document.getElementById(`comment-${commentId}`);
            if (!targetCommentElement) {
                console.error(`Could not find comment element with ID: comment-${commentId}`);
                return;
            }

            const isTargetANestedReply = targetCommentElement.parentElement.classList.contains('replies-container');

            form.elements.parent_id.value = commentId;
            const textarea = form.elements.content;

            if (isTargetANestedReply) {
                textarea.value = `@${username} `;
            } else {
                textarea.value = '';
            }

            textarea.focus();
            const end = textarea.value.length;
            textarea.setSelectionRange(end, end);

            const indicator = document.getElementById(`reply-indicator-${postId}`);
            indicator.querySelector('span').textContent = `Replying to @${username}`;
            indicator.classList.remove('hidden');
            indicator.classList.add('flex');

            if (targetCommentElement) {
                const repliesContainer = targetCommentElement.querySelector('.replies-container');
                if (repliesContainer && repliesContainer.classList.contains('hidden')) {
                    const toggleButton = targetCommentElement.querySelector('.view-replies-button');
                    if (toggleButton) {
                        toggleButton.click();
                    }
                }
            }
        }

        function cancelReply(postId) {
            const form = document.getElementById(`comment-form-${postId}`);
            if (!form) return;

            form.elements.parent_id.value = '';
            form.elements.content.value = '';

            const indicator = document.getElementById(`reply-indicator-${postId}`);
            indicator.classList.add('hidden');
            indicator.classList.remove('flex');
        }

        function canDeleteComment(comment) {
            const currentUserId = {{ Auth::id() ?? 'null' }};
            if (currentUserId === null) return false;
            const commentOwnerId = parseInt(comment.user_id, 10);
            const postOwnerId = comment.post ? parseInt(comment.post.user_id, 10) : null;
            if (commentOwnerId === currentUserId) {
                return true;
            }
            return postOwnerId !== null && postOwnerId === currentUserId;
        }

        async function toggleCommentLike(commentId, buttonElement) {
            if (!{{ Auth::check() ? 'true' : 'false' }}) {
                if (window.showToast) window.showToast(window.translations.js_login_to_like_comment || 'Please login to like comments.', 'warning');
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const url = `/comments/${commentId}/toggle-like`;

            const heartIcon = buttonElement.querySelector('.comment-like-icon');
            const likesCountSpan = document.getElementById(`comment-likes-count-${commentId}`);

            const originallyLiked = buttonElement.classList.contains('text-red-500');
            const originalLikesCount = parseInt(likesCountSpan.textContent.trim());

            const filledHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />';
            const outlineHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656zM10 15.93l5.828-5.828a2 2 0 10-2.828-2.828L10 10.274l-2.828-2.829a2 2 0 00-2.828 2.828L10 15.93z" clip-rule="evenodd" />';

            let newLikesCount;
            if (originallyLiked) {
                buttonElement.classList.remove('text-red-500', 'hover:bg-red-100');
                buttonElement.classList.add('text-gray-400', 'hover:text-red-500', 'hover:bg-red-50');
                heartIcon.innerHTML = outlineHeartSVG;
                buttonElement.title = window.translations.like_comment_title || 'Like';
                newLikesCount = Math.max(0, originalLikesCount - 1);
            } else {
                buttonElement.classList.remove('text-gray-400', 'hover:text-red-500', 'hover:bg-red-50');
                buttonElement.classList.add('text-red-500', 'hover:bg-red-100');
                heartIcon.innerHTML = filledHeartSVG;
                buttonElement.title = window.translations.unlike_comment_title || 'Unlike';
                newLikesCount = originalLikesCount + 1;
            }
            likesCountSpan.textContent = newLikesCount;
            likesCountSpan.className = `ml-1.5 comment-likes-count font-medium tabular-nums ${newLikesCount === 0 ? 'text-gray-400' : 'text-gray-700'}`;


            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || data.error_dev || 'Failed to toggle like.');
                }

                buttonElement.classList.toggle('text-red-500', data.is_liked);
                buttonElement.classList.toggle('hover:bg-red-100', data.is_liked);
                buttonElement.classList.toggle('text-gray-400', !data.is_liked);
                buttonElement.classList.toggle('hover:text-red-500', !data.is_liked);
                buttonElement.classList.toggle('hover:bg-red-50', !data.is_liked);


                if (data.is_liked) {
                    heartIcon.innerHTML = filledHeartSVG;
                    buttonElement.title = window.translations.unlike_comment_title || 'Unlike';
                } else {
                    heartIcon.innerHTML = outlineHeartSVG;
                    buttonElement.title = window.translations.like_comment_title || 'Like';
                }
                likesCountSpan.textContent = data.likes_count;
                likesCountSpan.className = `ml-1.5 comment-likes-count font-medium tabular-nums ${parseInt(data.likes_count) === 0 ? 'text-gray-400' : 'text-gray-700'}`;


                if (window.showToast && data.message_user) {
                    // window.showToast(data.message_user, 'success');
                }

            } catch (error) {
                console.error('Error toggling comment like:', error);
                if (window.showToast) window.showToast(error.message || (window.translations.js_error_liking_comment || 'Could not update like.'), 'error');

                buttonElement.classList.toggle('text-red-500', originallyLiked);
                buttonElement.classList.toggle('hover:bg-red-100', originallyLiked);
                buttonElement.classList.toggle('text-gray-400', !originallyLiked);
                buttonElement.classList.toggle('hover:text-red-500', !originallyLiked);
                buttonElement.classList.toggle('hover:bg-red-50', !originallyLiked);


                if (originallyLiked) {
                    heartIcon.innerHTML = filledHeartSVG;
                    buttonElement.title = window.translations.unlike_comment_title || 'Unlike';
                } else {
                    heartIcon.innerHTML = outlineHeartSVG;
                    buttonElement.title = window.translations.like_comment_title || 'Like';
                }
                likesCountSpan.textContent = originalLikesCount;
                likesCountSpan.className = `ml-1.5 comment-likes-count font-medium tabular-nums ${originalLikesCount === 0 ? 'text-gray-400' : 'text-gray-700'}`;
            }
        }

        function animateComments(container) {
            const comments = container.querySelectorAll(':scope > .comment:not(.visible)');

            comments.forEach((comment, index) => {
                const delay = Math.min(index * 80, 800);
                setTimeout(() => {
                    comment.classList.add('visible');
                }, delay);
            });
        }

        function renderPagination(paginationData, postId, containerElement) {
            containerElement.innerHTML = '';

            if (!paginationData || paginationData.last_page <= 1) {
                return;
            }

            const paginationNav = document.createElement('nav');
            paginationNav.setAttribute('aria-label', 'Comments pagination');
            const paginationList = document.createElement('ul');
            paginationList.className = 'pagination';

            if (paginationData.current_page > 1) {
                paginationList.appendChild(createPageLink('&laquo;', paginationData.current_page - 1, postId, false, window.i18n.pagination.previous));
            }

            for (let i = 1; i <= paginationData.last_page; i++) {
                paginationList.appendChild(createPageLink(i.toString(), i, postId, i === paginationData.current_page, `Go to page ${i}`));
            }

            if (paginationData.current_page < paginationData.last_page) {
                paginationList.appendChild(createPageLink('&raquo;', paginationData.current_page + 1, postId, false, window.i18n.pagination.next));
            }

            paginationNav.appendChild(paginationList);
            containerElement.appendChild(paginationNav);
        }

        function createPageLink(text, page, postId, isActive = false, ariaLabel = '') {
            const pageItem = document.createElement('li');
            pageItem.className = `page-item ${isActive ? 'active' : ''}`;

            const link = document.createElement('a');
            link.className = 'page-link';
            link.href = '#';
            link.innerHTML = text;
            if (ariaLabel) link.setAttribute('aria-label', ariaLabel);
            if (isActive) link.setAttribute('aria-current', 'page');


            link.onclick = (e) => {
                e.preventDefault();
                const commentsSection = document.getElementById(`comments-section-${postId}`);
                if (commentsSection) {
                    // commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    const postElement = document.getElementById(`post-${postId}`);
                    if (postElement) {
                        window.scrollTo({top: postElement.offsetTop - 80, behavior: 'smooth'});
                    }

                }
                loadComments(postId, page);
            };

            pageItem.appendChild(link);
            return pageItem;
        }


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
                    observer.unobserve(scrollTrigger);
                } else {
                    currentPage[type]++;
                    loadingIndicator.classList.remove('hidden');
                    observer.unobserve(scrollTrigger);
                }

                const fetchUrl = `${url}?page=${currentPage[type]}`;

                try {
                    const response = await fetch(fetchUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });

                    if (response.status === 401) {
                        const message = window.i18n.profile.js.login_to_see_posts.replace(':username', window.profileUsername);
                        postsContainer.innerHTML = `<p class="text-gray-500 text-center py-8">${message}</p>`;
                        hasMorePages[type] = false;
                        return;
                    }

                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    const data = await response.json();

                    if (!loadMore) {
                        postsContainer.innerHTML = data.html || `<p class="text-gray-500 text-center py-8">${window.i18n.profile.js.no_posts_found}</p>`;
                    } else {
                        postsContainer.insertAdjacentHTML('beforeend', data.html || '');
                    }
                    initializePostInteractions();

                    hasMorePages[type] = data.hasMorePages;

                    if (hasMorePages[type]) {
                        observer.observe(scrollTrigger);
                    }

                    if (postsContainer.children.length === 0 || (postsContainer.children.length === 1 && postsContainer.children[0].tagName === 'P')) {
                        postsContainer.innerHTML = `<p class="text-gray-500 text-center py-8">${window.i18n.profile.js.no_posts_found}</p>`;
                        observer.unobserve(scrollTrigger);
                    }

                } catch (error) {
                    console.error('Error loading posts:', error);
                    if (!loadMore) {
                        postsContainer.innerHTML = `<p class="text-red-500 text-center py-8">${window.i18n.profile.js.error_loading_posts}</p>`;
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

@section('structured_data')
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Person",
            "name": "{{ addslashes($displayName) }}",
    "alternateName": "{{ '@' . $user->username }}",
    "url": "{{ route('profile.show', ['username' => $user->username]) }}",
        @if($profilePic && !Str::contains($profilePic, 'default-pfp.png'))
            "image": "{{ $profilePic }}",
        @endif
        "description": "{{ addslashes(__('messages.profile.meta_description', ['username' => $user->username])) }}",
    "mainEntityOfPage": {
        "@type": "ProfilePage",
        "@id": "{{ route('profile.show', ['username' => $user->username]) }}"
    },
    "interactionStatistic": [
        {
            "@type": "InteractionCounter",
            "interactionType": { "@type": "WriteAction" },
            "userInteractionCount": {{ $user->posts_count }}
        },
        {
            "@type": "InteractionCounter",
            "interactionType": { "@type": "LikeAction" },
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
        }
    </script>
@endsection
