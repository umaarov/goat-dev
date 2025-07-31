@extends('layouts.app')
@section('title', $post->question . ' - GOAT.uz')
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
                    "name": {!! json_encode(Str::limit($post->question, 50)) !!}
        }
    ]
},
{
    "@@type": "SocialMediaPosting",
    "headline": {!! json_encode($post->question) !!},
        @if($post->ai_generated_context)
            "description": {!! json_encode(Str::limit($post->ai_generated_context, 160)) !!},
                "articleBody": {!! json_encode($post->ai_generated_context) !!},
        @endif
        @if(!empty($post->ai_generated_tags))
            "keywords": {!! json_encode($post->ai_generated_tags) !!},
        @endif
        "url": "{{ $postUrl }}",
            "datePublished": "{{ $post->created_at->toIso8601String() }}",
            "author": {
                "@@type": "Person",
                "name": "{{ '@' . $post->user->username }}",
                "url": "{{ route('profile.show', $post->user->username) }}"
            },
        @if($post->option_one_image || $post->option_two_image)
            @php
                $images = [];
                if($post->option_one_image) $images[] = asset('storage/' . $post->option_one_image);
                if($post->option_two_image) $images[] = asset('storage/' . $post->option_two_image);
            @endphp
            "image": {!! json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!},
        @endif
        "interactionStatistic": [
            {
                "@@type": "InteractionCounter",
                "interactionType": { "@@type": "CommentAction" },
                "userInteractionCount": {{ $post->comments_count }}
        },
        {
            "@@type": "InteractionCounter",
            "interactionType": { "@@type": "LikeAction" },
            "userInteractionCount": {{ $post->total_votes }}
        }
    ],
    "publisher": {
        "@@type": "Organization",
        "name": "GOAT.uz",
        "logo": {
            "@@type": "ImageObject",
            "url": "{{ asset('images/icons/icon-512x512.png') }}"
                }
            }
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
