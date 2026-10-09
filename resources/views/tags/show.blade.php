@extends('layouts.app')

@php
    $first = $posts->first();
    $examples = $posts->take(3)->pluck('question')->map(fn ($q) => \Illuminate\Support\Str::limit($q, 60))->implode(' · ');
    $description = __('messages.tags_page_description', ['count' => $posts->total(), 'tag' => $tag->name]).($examples !== '' ? ' '.$examples : '');
    $url = route('tags.show', ['slug' => $tag->slug]);
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'CollectionPage',
                'name' => '#'.$tag->name,
                'description' => $description,
                'url' => $url,
                'isPartOf' => ['@id' => 'https://www.goat.uz#website'],
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => $posts->total(),
                    'itemListElement' => $posts->values()->map(fn ($post, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'url' => route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]),
                        'name' => $post->question,
                    ])->all(),
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'GOAT.uz', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('messages.tags_index_heading'), 'item' => route('tags.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => '#'.$tag->name, 'item' => $url],
                ],
            ],
        ],
    ];
@endphp

@section('title', __('messages.tags_page_title', ['tag' => $tag->name]))
@section('meta_description', $description)
@section('meta_robots', $indexable ? 'index, follow' : 'noindex, follow')
@section('canonical_url', $url)
@if($first)
    @section('og_image', route('posts.card', ['username' => $first->user->username, 'post' => $first->id, 'v' => app(\App\Services\PostCardImage::class)->version($first)]))
@endif

@push('schema')
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <nav class="mb-3 text-sm text-blue-800 dark:text-blue-400">
        <a href="{{ route('tags.index') }}" class="hover:underline">&larr; {{ __('messages.tags_all') }}</a>
    </nav>

    <h1 class="mb-1 text-center text-2xl font-bold text-gray-900 dark:text-gray-100">#{{ $tag->name }}</h1>
    <p class="mb-4 text-center text-sm text-gray-600 dark:text-gray-400">{{ __('messages.tags_count', ['count' => $posts->total()]) }}</p>

    <div id="posts-container">
        @foreach($posts as $post)
            @include('partials.post-card', ['post' => $post, 'isFirst' => $loop->first])
        @endforeach
    </div>

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
@endsection
