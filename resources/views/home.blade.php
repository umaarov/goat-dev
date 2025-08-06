@extends('layouts.app')

@section('title', __('messages.home.page_title_posts'))
@section('meta_description', __('messages.home.meta_description'))

@push('schema')
    @if ($posts->isNotEmpty())
        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "ItemList",
                "name": "Trending Polls",
                "description": "The latest and most popular polls on GOAT.uz.",
                "itemListElement": [
            @foreach($posts as $post)
                {
                    "@@type": "ListItem",
                    "position": {{ $loop->iteration }},
                    "url": "{{ route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]) }}"
                }
                {{ !$loop->last ? ',' : '' }}
            @endforeach
            ]
        }
        </script>
    @endif
@endpush

@section('content')
    <a href="https://t.me/voteongoat" target="_blank" rel="noopener noreferrer"
       class="flex items-center justify-center gap-x-2.5 text-sm text-center p-3 mb-3 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition-colors duration-200 border border-blue-300">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.57c-.28 1.13-1.04 1.4-1.74.88L14.25 16l-4.12 3.9c-.78.76-1.36.37-1.57-.49z"/>
        </svg>
        <span class="font-medium">{{ __('messages.app.telegram_ad') }}</span>
    </a>

    <div id="posts-wrapper">
        <div id="posts-loading-shimmer">
            @for ($i = 0; $i < 5; $i++)
                @include('partials.post-card-shimmer')
            @endfor
        </div>


        <div id="posts-container" class="hidden">
            @if ($posts->count() > 0)
                @foreach($posts as $post)
                    @include('partials.post-card', ['post' => $post])
                    @if (($loop->iteration % 4) == 0)
                            <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2989575196315667"
                                    crossorigin="anonymous"></script>
                            <ins class="adsbygoogle"
                                 style="display:block"
                                 data-ad-format="fluid"
                                 data-ad-layout-key="-6t+ed+2i-1n-4w"
                                 data-ad-client="ca-pub-2989575196315667"
                                 data-ad-slot="7674157999"></ins>
                            <script>
                                (adsbygoogle = window.adsbygoogle || []).push({});
                            </script>
                    @endif
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
    <div class="sr-only">
        {{ $posts->links() }}
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
                const url = `{{ route('posts.load_more') }}?page=${nextPage}${filter ? '&filter=' + filter : ''}`;

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
