@extends('layouts.app')

@php
    $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);
    $ogImage = route('posts.card', ['username' => $post->user->username, 'post' => $post->id, 'v' => app(\App\Services\PostCardImage::class)->version($post)]);
    $metaDescription = $post->ai_generated_context ?: __('messages.post_meta_description', [
        'question' => $post->question,
        'one' => $post->option_one_title,
        'two' => $post->option_two_title,
        'votes' => (int) $post->total_votes,
    ]);
@endphp

@section('title', Str::limit($post->question, 49, '…') . ' - GOAT.uz')
@section('meta_description', Str::limit($metaDescription, 160))
@section('canonical_url', $postUrl)
@section('og_type', 'article')
@section('og_image', $ogImage)

@push('schema')
    <script type="application/ld+json">{!! \App\Support\QuestionSchema::json(\App\Support\QuestionSchema::for($post, $postUrl, app()->getLocale())) !!}</script>
@endpush

@section('content')
    <div class="container mx-auto">
        @include('partials.post-card', ['post' => $post, 'headingTag' => 'h1'])

        @php $related = \App\Support\RelatedQuestions::for($post); @endphp
        @if ($related !== [])
            <nav class="px-4 pb-8" aria-labelledby="related-heading">
                <h2 id="related-heading" class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-2">{{ __('messages.related_questions_heading') }}</h2>
                <ul class="space-y-1">
                    @foreach ($related as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="text-blue-600 dark:text-blue-400 underline underline-offset-2 hover:no-underline">{{ $item['question'] }}</a>
                            <span class="text-sm text-gray-500 dark:text-gray-400">· {{ __('messages.related_votes', ['count' => $item['votes']]) }}</span>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
@endsection
