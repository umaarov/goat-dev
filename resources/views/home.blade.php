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
            @if ($posts->count() > 0)
                @foreach($posts as $post)
                    @include('partials.post-card', [
                        'post' => $post,
                        'isFirst' => $loop->first,
                        'showManagementOptions' => $showManagementOptions ?? false,
                        'profileOwnerToDisplay' => $profileOwnerToDisplay ?? null,
                    ])
                @endforeach
            @else
                <div class="text-center p-8 bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)]">
                    <p>{{ __('messages.app.no_posts_found') }}</p>
                </div>
            @endif
        </div>

        <div id="infinite-scroll-trigger" class=""></div>

        <div id="loading-indicator" class="hidden text-center">
            @include('partials.post-card-shimmer')
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shimmer = document.getElementById('posts-loading-shimmer');
            const container = document.getElementById('posts-container');
            const noPostsMessage = '{{ $posts->isEmpty() }}';

            if (shimmer && container) {
                if (noPostsMessage) {
                    shimmer.style.display = 'none';
                    container.classList.remove('hidden');
                } else {
                    setTimeout(() => {
                        shimmer.style.display = 'none';
                        container.classList.remove('hidden');
                    }, 250);
                }
            }

            const postContainer = document.getElementById('posts-container');
            const trigger = document.getElementById('infinite-scroll-trigger');
            const loadingIndicator = document.getElementById('loading-indicator');

            let nextPage = 2;
            let isLoading = false;
            let hasMorePages = {{ $posts->hasMorePages() ? 'true' : 'false' }};

            if (!hasMorePages) {
                trigger.style.display = 'none';
            }

            const loadMorePosts = async () => {
                if (isLoading || !hasMorePages) {
                    return;
                }

                isLoading = true;
                loadingIndicator.classList.remove('hidden');

                const filter = new URLSearchParams(window.location.search).get('filter') || '';
                const url = `{{ route('home') }}?page=${nextPage}${filter ? '&filter=' + filter : ''}`;

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.html.trim().length > 0) {
                        postContainer.insertAdjacentHTML('beforeend', data.html);

                        document.dispatchEvent(new Event('posts-loaded'));

                        nextPage++;
                        hasMorePages = data.hasMorePages;

                        if (!hasMorePages) {
                            trigger.style.display = 'none';
                        }
                    } else {
                        hasMorePages = false;
                        trigger.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Error loading more posts:', error);
                    trigger.style.display = 'none';
                } finally {
                    isLoading = false;
                    loadingIndicator.classList.add('hidden');
                }
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMorePosts();
                }
            }, {
                rootMargin: '200px',
            });

            if (hasMorePages) {
                observer.observe(trigger);
            }
        });
    </script>
@endpush
