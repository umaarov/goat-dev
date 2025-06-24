@php
    use Illuminate\Support\Str;
@endphp
@extends('layouts.app')

@section('title', __('messages.ratings.title'))
@section('meta_description', __('messages.ratings.meta_description'))

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-8 text-center">
            <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">{{ __('messages.ratings.main_title') }}</h1>
            <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">{{ __('messages.ratings.subtitle') }}</p>
        </header>

        <div x-data="{ tab: 'post_votes' }">
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex flex-wrap justify-center space-x-4 sm:space-x-8" aria-label="Tabs">
                    <a href="#" @click.prevent="tab = 'post_votes'"
                       :class="tab === 'post_votes' ? 'border-blue-600 text-blue-700 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                       class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150">
                        {{ __('messages.ratings.tabs.top_post_votes') }}
                    </a>
                    <a href="#" @click.prevent="tab = 'post_count'"
                       :class="tab === 'post_count' ? 'border-blue-600 text-blue-700 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                       class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150">
                        {{ __('messages.ratings.tabs.top_post_creators') }}
                    </a>
                    <a href="#" @click.prevent="tab = 'comment_likes'"
                       :class="tab === 'comment_likes' ? 'border-blue-600 text-blue-700 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                       class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150">
                        {{ __('messages.ratings.tabs.top_comment_likes') }}
                    </a>
                    <a href="#" @click.prevent="tab = 'comment_count'"
                       :class="tab === 'comment_count' ? 'border-blue-600 text-blue-700 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                       class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150">
                        {{ __('messages.ratings.tabs.top_commentators') }}
                    </a>
                </nav>
            </div>

            <div class="transition-all duration-300">
                <div x-show="tab === 'post_votes'" x-cloak>
                    @include('rating.partials.user-list', [
                        'users' => $topByPostVotes,
                        'title' => __('messages.ratings.tabs.top_post_votes'),
                        'description' => __('messages.ratings.descriptions.post_votes'),
                        'value_accessor' => 'total_post_votes',
                        'value_label_singular' => __('messages.ratings.labels.vote_singular'),
                        'value_label_plural' => __('messages.ratings.labels.vote_plural')
                    ])
                </div>
                <div x-show="tab === 'post_count'" x-cloak>
                    @include('rating.partials.user-list', [
                        'users' => $topByPostCount,
                        'title' => __('messages.ratings.tabs.top_post_creators'),
                        'description' => __('messages.ratings.descriptions.post_count'),
                        'value_accessor' => 'posts_count',
                        'value_label_singular' => __('messages.ratings.labels.post_singular'),
                        'value_label_plural' => __('messages.ratings.labels.post_plural')
                    ])
                </div>
                <div x-show="tab === 'comment_likes'" x-cloak>
                    @include('rating.partials.user-list', [
                        'users' => $topByCommentLikes,
                        'title' => __('messages.ratings.tabs.top_comment_likes'),
                        'description' => __('messages.ratings.descriptions.comment_likes'),
                        'value_accessor' => 'total_comment_likes',
                        'value_label_singular' => __('messages.ratings.labels.like_singular'),
                        'value_label_plural' => __('messages.ratings.labels.like_plural')
                    ])
                </div>
                <div x-show="tab === 'comment_count'" x-cloak>
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
    </style>
@endpush
