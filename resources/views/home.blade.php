@extends('layouts.app')

@section('title', __('messages.home.page_title_posts'))
@section('meta_description', __('messages.home.meta_description'))

@push('schema')
    <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "WebPage",
          "url": "{{ route('home') }}",
          "name": "{{ __('messages.home.page_title_posts') }}",
          "description": "{{ __('messages.home.meta_description') }}",
          "isPartOf": {
            "@id": "{{ config('app.url', 'https://goat.uz') }}#website"
        }
    }
    </script>
@endpush

@section('content')
    <div id="posts-wrapper">
        <div id="posts-loading-shimmer">
            @for ($i = 0; $i < 5; $i++)
                @include('partials.post-card-shimmer')
            @endfor
        </div>

        <div id="posts-container" class="hidden">
            @forelse ($posts as $post)
                @include('partials.post-card', ['post' => $post])
            @empty
                <div class="text-center p-8 bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)]">
                    <p>{{ __('messages.app.no_posts_found') }}</p>
                </div>
            @endforelse

            <div class="pagination">
                {{ $posts->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shimmer = document.getElementById('posts-loading-shimmer');
            const container = document.getElementById('posts-container');
            const noPostsMessage = container.querySelector('.text-center p');

            if (shimmer && container) {
                if (noPostsMessage && '{{ $posts->isEmpty() }}') {
                    shimmer.style.display = 'none';
                    container.classList.remove('hidden');
                } else {
                    setTimeout(() => {
                        shimmer.style.display = 'none';
                        container.classList.remove('hidden');
                    }, 250);
                }
            }
        });
    </script>
@endpush
