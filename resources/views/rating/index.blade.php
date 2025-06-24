@php
    use Illuminate\Support\Str;
@endphp
@extends('layouts.app')

@section('title', __('messages.ratings.title'))
@section('meta_description', __('messages.ratings.meta_description'))

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <header class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.ratings.main_title') }}</h1>
            <p class="mt-1 text-md text-gray-500">{{ __('messages.ratings.subtitle') }}</p>
        </header>

        <div x-data="{ tab: 'post_votes', isLoading: true }" x-init="setTimeout(() => isLoading = false, 500)">
            {{-- Tabs --}}
            <div class="mb-6">
                <div class="bg-gray-200/75 rounded-lg p-1">
                    <div class="flex flex-wrap justify-center gap-1">
                        <button @click.prevent="tab = 'post_votes'"
                                :class="tab === 'post_votes' ? 'bg-white' : 'text-gray-600 hover:bg-white/70'"
                                class="flex-grow text-center whitespace-nowrap py-2 px-3 rounded-md font-medium text-sm transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                            {{ __('messages.ratings.tabs.top_post_votes') }}
                        </button>
                        <button @click.prevent="tab = 'post_count'"
                                :class="tab === 'post_count' ? 'bg-white' : 'text-gray-600 hover:bg-white/70'"
                                class="flex-grow text-center whitespace-nowrap py-2 px-3 rounded-md font-medium text-sm transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                            {{ __('messages.ratings.tabs.top_post_creators') }}
                        </button>
                        <button @click.prevent="tab = 'comment_likes'"
                                :class="tab === 'comment_likes' ? 'bg-white' : 'text-gray-600 hover:bg-white/70'"
                                class="flex-grow text-center whitespace-nowrap py-2 px-3 rounded-md font-medium text-sm transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                            {{ __('messages.ratings.tabs.top_comment_likes') }}
                        </button>
                        <button @click.prevent="tab = 'comment_count'"
                                :class="tab === 'comment_count' ? 'bg-white' : 'text-gray-600 hover:bg-white/70'"
                                class="flex-grow text-center whitespace-nowrap py-2 px-3 rounded-md font-medium text-sm transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                            {{ __('messages.ratings.tabs.top_commentators') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Shimmer Loading State --}}
            <div x-show="isLoading" x-cloak>
                @include('rating.partials.shimmer-list')
            </div>

            {{-- Main Content --}}
            <div x-show="!isLoading" x-cloak class="transition-opacity duration-300">
                <div x-show="tab === 'post_votes'">
                    @include('rating.partials.user-list', [
                        'users' => $topByPostVotes,
                        'title' => __('messages.ratings.tabs.top_post_votes'),
                        'description' => __('messages.ratings.descriptions.post_votes'),
                        'value_accessor' => 'total_post_votes',
                        'value_label_singular' => __('messages.ratings.labels.vote_singular'),
                        'value_label_plural' => __('messages.ratings.labels.vote_plural')
                    ])
                </div>
                <div x-show="tab === 'post_count'">
                    @include('rating.partials.user-list', [
                        'users' => $topByPostCount,
                        'title' => __('messages.ratings.tabs.top_post_creators'),
                        'description' => __('messages.ratings.descriptions.post_count'),
                        'value_accessor' => 'posts_count',
                        'value_label_singular' => __('messages.ratings.labels.post_singular'),
                        'value_label_plural' => __('messages.ratings.labels.post_plural')
                    ])
                </div>
                <div x-show="tab === 'comment_likes'">
                    @include('rating.partials.user-list', [
                        'users' => $topByCommentLikes,
                        'title' => __('messages.ratings.tabs.top_comment_likes'),
                        'description' => __('messages.ratings.descriptions.comment_likes'),
                        'value_accessor' => 'total_comment_likes',
                        'value_label_singular' => __('messages.ratings.labels.like_singular'),
                        'value_label_plural' => __('messages.ratings.labels.like_plural')
                    ])
                </div>
                <div x-show="tab === 'comment_count'">
                    @include('rating.partials.user-list', [
                        'users' => $topByCommentCount,
                        'title' => __('messages.ratings.tabs.top_commentators'),
                        'description' => __('messages.ratings.descriptions.comment_count'),
                        'value_accessor' => 'comments_count',
                        'value_label_singular' => __('messages.ratings.labels.comment_singular'),
                        'value_label_plural' => __('messages.ratings.labels.comment_plural')
                    ])
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .podium-1 { color: #FFD700; }
        .podium-2 { color: #C0C0C0; }
        .podium-3 { color: #CD7F32; }

        .shimmer-bg {
            animation-duration: 1.5s;
            animation-fill-mode: forwards;
            animation-iteration-count: infinite;
            animation-name: shimmer;
            animation-timing-function: linear;
            background-color: #f3f4f6;
            background-image: linear-gradient(to right, #f3f4f6 0%, #e5e7eb 50%, #f3f4f6 100%);
            background-repeat: no-repeat;
            background-size: 200% 100%;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
@endpush
