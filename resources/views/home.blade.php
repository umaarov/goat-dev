@extends('layouts.app')

@section('title', __('messages.home.page_title_posts'))
@section('meta_description', __('messages.home.meta_description'))

@push('styles')
    <style>
        .end-of-feed-indicator {
            text-align: center;
            padding: 3rem 1.5rem 4rem;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
            pointer-events: none;
            will-change: opacity, transform;
        }

        .end-of-feed-indicator.visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .end-of-feed-content {
            max-width: 450px;
            margin: 0 auto;
        }

        .end-of-feed-icon {
            width: 4rem;
            height: 4rem;
            color: #22c55e;
            margin: 0 auto 1.25rem;
        }

        .end-of-feed-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .end-of-feed-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .end-of-feed-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
        }

        .btn-secondary {
            background-color: white;
            color: #4b5563;
            border-color: #d1d5db;
        }
    </style>
@endpush

@section('content')
    <div id="posts-wrapper">
        <div id="posts-container"></div>

        <div id="posts-loading-shimmer">
            @for ($i = 0; $i < 5; $i++)
                @include('partials.post-card-shimmer')
            @endfor
        </div>

        <div id="loading-indicator" class="text-center p-4" style="display: none;">
            <p class="text-gray-600">{{ __('messages.app.loading_more_posts') }}</p>
        </div>

        <div id="sentinel" style="height: 10px;"></div>

        <div id="no-more-posts" class="end-of-feed-indicator">
            <div class="end-of-feed-content">
                <svg class="end-of-feed-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="end-of-feed-title">{{ __('messages.app.all_caught_up') }}</h3>
                <p class="end-of-feed-subtitle">{{ __('messages.app.you_have_seen_all_posts') }}</p>
                <div class="end-of-feed-actions">
                    <a href="{{ route('posts.create') }}"
                       class="bg-blue-800 btn btn-primary">{{ __('messages.app.create_new_post') }}</a>
                    <button type="button" id="back-to-top"
                            class="bg-blue-800 btn btn-secondary">{{ __('messages.app.back_to_top') }}</button>
                </div>
            </div>
        </div>

        <div id="posts-initial-empty"
             class="text-center p-8 bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)]"
             style="display: none;">
            <p>{{ __('messages.app.no_posts_found') }}</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('posts-container');
            const shimmer = document.getElementById('posts-loading-shimmer');
            const sentinel = document.getElementById('sentinel');
            const loadingIndicator = document.getElementById('loading-indicator');
            const noMorePostsMessage = document.getElementById('no-more-posts');
            const initialEmptyMessage = document.getElementById('posts-initial-empty');
            const backToTopButton = document.getElementById('back-to-top');

            let page = 1;
            let isLoading = false;
            let hasMorePages = true;

            const loadPosts = async () => {
                if (isLoading || !hasMorePages) return;
                isLoading = true;
                if (page === 1) {
                    shimmer.style.display = 'block';
                } else {
                    loadingIndicator.style.display = 'block';
                }

                try {
                    const url = new URL('{{ route('posts.load') }}');
                    url.searchParams.append('page', page);
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (page === 1) shimmer.style.display = 'none';
                    if (!response.ok) throw new Error('Network response was not ok.');
                    const data = await response.json();
                    if (data.html) {
                        container.insertAdjacentHTML('beforeend', data.html);
                        page++;
                    }
                    hasMorePages = data.hasMorePages;

                    if (!hasMorePages) {
                        observer.disconnect();
                        if (container.children.length > 0) {
                            noMorePostsMessage.classList.add('visible');
                        }
                    }
                    if (container.children.length === 0 && !hasMorePages) {
                        initialEmptyMessage.style.display = 'block';
                    }
                } catch (error) {
                    console.error("Failed to load posts:", error);
                    hasMorePages = false;
                    observer.disconnect();
                    if (page === 1) {
                        shimmer.style.display = 'none';
                        initialEmptyMessage.querySelector('p').innerText = '{{ __("messages.error_loading_posts") }}';
                        initialEmptyMessage.style.display = 'block';
                    } else {
                        loadingIndicator.innerText = '{{ __("messages.error_loading_posts") }}';
                        loadingIndicator.style.display = 'block';
                    }
                } finally {
                    isLoading = false;
                    if (page > 1) loadingIndicator.style.display = 'none';
                }
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadPosts();
                }
            }, {rootMargin: '400px'});

            observer.observe(sentinel);

            if (backToTopButton) {
                backToTopButton.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            loadPosts();
        });
    </script>
@endpush
