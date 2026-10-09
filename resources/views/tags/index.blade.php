@extends('layouts.app')

@section('title', __('messages.tags_index_title'))
@section('meta_description', __('messages.tags_index_intro'))
@section('canonical_url', route('tags.index'))

@section('content')
    <h1 class="mb-1 text-center text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('messages.tags_index_heading') }}</h1>
    <p class="mb-5 text-center text-sm text-gray-600 dark:text-gray-400">{{ __('messages.tags_index_intro') }}</p>

    <div class="flex flex-wrap justify-center gap-2">
        @foreach($tags as $tag)
            <a href="{{ route('tags.show', ['slug' => $tag->slug]) }}"
               class="block bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold px-3 py-1.5 rounded-full shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] dark:shadow-[inset_0_0_0_0.5px_rgba(255,255,255,0.2)] hover:bg-blue-100 dark:hover:bg-blue-900/50 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-200">
                #{{ $tag->name }} <span class="text-gray-500 dark:text-gray-400 font-normal">{{ $tag->posts_count }}</span>
            </a>
        @endforeach
    </div>
@endsection
