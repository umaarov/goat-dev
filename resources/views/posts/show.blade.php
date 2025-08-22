@extends('layouts.app')

@section('title', $post->question . ' - GOAT.uz')

@php
    $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);
    $ogImage = $post->option_one_image ? asset('storage/' . $post->option_one_image) : ($post->option_two_image ? asset('storage/' . $post->option_two_image) : asset('images/goat.jpg'));
@endphp

@section('title', $post->question . ' - GOAT.uz')
@section('meta_description', Str::limit($post->ai_generated_context ?? $post->question, 160))
@section('canonical_url', $postUrl)
@section('og_type', 'article')
@section('og_image', $ogImage)

@section('meta_description', Str::limit($post->ai_generated_context ?? $post->question, 160))
@php $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]); @endphp
@push('schema')
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
    "@@graph": [
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
                    "name": "{{ '@' . $post->user->username }}",
                    "item": "{{ route('profile.show', $post->user->username) }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 3,
                    "name": {!! json_encode(Str::limit($post->question, 50)) !!},
                    "item": "{{ $postUrl }}"
                }
            ]
        },
        {
            "@@type": "Question",
            "name": {!! json_encode($post->question) !!},
            "upvoteCount": {{ $post->total_votes }},
            "answerCount": 2,
        @if($post->ai_generated_context)
            "text": {!! json_encode($post->ai_generated_context) !!},
        @endif
        "dateCreated": "{{ $post->created_at->toIso8601String() }}",
            "author": {
                "@@type": "Person",
                "name": "{{ '@' . $post->user->username }}",
                "url": "{{ route('profile.show', $post->user->username) }}"
            },
            "suggestedAnswer": {
                "@@type": "Answer",
                "text": {!! json_encode($post->option_one_text) !!},
                "upvoteCount": {{ $post->option_one_votes }},
                "url": "{{ $postUrl }}#option1"
        @if($post->option_one_image)
            ,
            "image": "{{ asset('storage/' . $post->option_one_image) }}"
        @endif
        },
        "acceptedAnswer": {
            "@@type": "Answer",
            "text": {!! json_encode($post->option_two_text) !!},
                "upvoteCount": {{ $post->option_two_votes }},
                "url": "{{ $postUrl }}#option2"
        @if($post->option_two_image)
            ,
            "image": "{{ asset('storage/' . $post->option_two_image) }}"
        @endif
        },
        "interactionStatistic": [
            {
                "@@type": "InteractionCounter",
                "interactionType": { "@@type": "CommentAction" },
                "userInteractionCount": {{ $post->comments_count }}
        }
    ],
    "publisher": {"@@id": "https://www.goat.uz#organization"}
}
]
}
    </script>
@endpush

@section('content')
    <div class="container mx-auto">
        @include('partials.post-card', ['post' => $post])
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const postElement = document.getElementById('post-{{ $post->id }}');
            if (postElement) {
                const commentsButton = postElement.querySelector('button[onclick^="toggleComments"]');
                if (commentsButton) {
                    commentsButton.click();
                }
            }
        });
    </script>
@endpush
