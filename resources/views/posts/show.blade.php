@extends('layouts.app')
@section('title', $post->question . ' - GOAT.uz')
@section('meta_description', Str::limit($post->ai_generated_context ?? $post->question, 160))

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
