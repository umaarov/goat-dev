@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', e($user->username) . "'s Profile")

@section('content')
    <div class="max-w-3xl mx-auto">
        <!-- Profile Header -->
        <div
            class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] border border-gray-100 overflow-hidden mb-6">
            <div class="p-6">
                <div class="flex items-start">
                    @php
                        $profilePic = $user->profile_picture
                            ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                                ? $user->profile_picture
                                : asset('storage/' . $user->profile_picture))
                            : asset('images/default-pfp.png');

                        $isVerified = in_array($user->username, ['goat', 'umarov']);
                    @endphp
                    <img src="{{ $profilePic }}" alt="{{ $user->username }}'s profile picture"
                         class="h-24 w-24 rounded-full object-cover border border-gray-200">

                    <div class="ml-6 flex-1">
                        @if($user->first_name)
                            <div class="flex items-center">
                                <h2 class="text-2xl font-semibold text-gray-800">{{ $user->first_name }} {{ $user->last_name }}</h2>
                                @if($isVerified)
                                    <span class="ml-1" title="Verified Account">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </span>
                                @endif
                            </div>
                        @endif
                        <div class="flex items-center">
                            <p class="text-gray-600">{{ "@$user->username" }}</p>
                        </div>
                        <p class="text-gray-500 text-sm">Joined: {{ $user->created_at->format('M d, Y') }}</p>

                        @if ($isOwnProfile)
                            <div class="mt-4">
                                <a href="{{ route('profile.edit') }}"
                                   class="px-4 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                    Edit Profile
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Post Tabs -->
        <div class="mb-4 border-b border-gray-200">
            <div class="flex">
                <button id="load-my-posts" data-url="{{ route('profile.posts.data', $user->username) }}"
                        class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                    {{ $isOwnProfile ? 'My Posts' : $user->username . "'s Posts" }}
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-800 transition-all duration-300"
                          id="my-posts-indicator"></span>
                </button>
                @if ($isOwnProfile)
                    <button id="load-voted-posts" data-url="{{ route('profile.voted.data', $user->username) }}"
                            class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                        Voted Posts
                        <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-transparent transition-all duration-300"
                              id="voted-posts-indicator"></span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Posts directly displayed -->
        <div id="posts-container" class="space-y-4">
            <p class="text-gray-500 text-center py-8">Loading posts...</p>
        </div>

        <!-- Load more button will be appended here by JavaScript -->
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const postsContainer = document.getElementById('posts-container');
            const myPostsButton = document.getElementById('load-my-posts');
            const myPostsIndicator = document.getElementById('my-posts-indicator');
            const votedPostsButton = document.getElementById('load-voted-posts');
            const votedPostsIndicator = votedPostsButton ? document.getElementById('voted-posts-indicator') : null;
            const buttons = [myPostsButton, votedPostsButton].filter(btn => btn != null);
            const indicators = [myPostsIndicator, votedPostsIndicator].filter(ind => ind != null);

            let currentPage = {};
            let isLoading = {};
            let hasMorePages = {};

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function setActiveTab(activeButton) {
                buttons.forEach(btn => {
                    btn.classList.remove('text-blue-800', 'font-semibold');
                    btn.classList.add('text-gray-700');
                });

                indicators.forEach(ind => {
                    ind.classList.remove('bg-blue-800');
                    ind.classList.add('bg-transparent');
                });

                if (activeButton) {
                    activeButton.classList.remove('text-gray-700');
                    activeButton.classList.add('text-blue-800', 'font-semibold');

                    // Find the matching indicator
                    const buttonId = activeButton.id;
                    const indicatorId = buttonId + '-indicator';
                    const indicator = document.getElementById(indicatorId);
                    if (indicator) {
                        indicator.classList.remove('bg-transparent');
                        indicator.classList.add('bg-blue-800');
                    }
                }
            }

            async function loadPosts(url, type, loadMore = false) {
                if (isLoading[type]) return;

                if (!loadMore) {
                    currentPage[type] = 1;
                    hasMorePages[type] = true;
                    postsContainer.innerHTML = '<p class="text-center py-4">Loading...</p>';
                } else {
                    if (!hasMorePages[type]) {
                        console.log('No more pages to load for', type);
                        return;
                    }
                    currentPage[type]++;
                }

                isLoading[type] = true;
                const fetchUrl = `${url}?page=${currentPage[type]}`;

                try {
                    const response = await fetch(fetchUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (!loadMore) {
                        postsContainer.innerHTML = data.html || '<p class="text-gray-500 text-center py-8">No posts found.</p>';
                    } else {
                        const existingLoadMoreButton = document.querySelector('.load-more-button');
                        if (existingLoadMoreButton) {
                            existingLoadMoreButton.remove();
                        }
                        postsContainer.insertAdjacentHTML('beforeend', data.html || '');
                    }

                    hasMorePages[type] = data.hasMorePages;

                    if (hasMorePages[type]) {
                        const loadMoreButton = document.createElement('button');
                        loadMoreButton.textContent = 'Load More';
                        loadMoreButton.classList.add('load-more-button', 'w-full', 'mt-6', 'py-3', 'bg-gray-100', 'text-gray-700', 'rounded-md', 'hover:bg-gray-200', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
                        loadMoreButton.dataset.url = url;
                        loadMoreButton.dataset.type = type;
                        loadMoreButton.onclick = () => loadPosts(url, type, true);
                        postsContainer.appendChild(loadMoreButton);
                    } else if (postsContainer.children.length === 0 && !loadMore) {
                        postsContainer.innerHTML = '<p class="text-gray-500 text-center py-8">No posts found.</p>';
                    }

                } catch (error) {
                    console.error('Error loading posts:', error);
                    postsContainer.innerHTML = '<p class="text-red-500 text-center py-8">Error loading posts. Please try again.</p>';
                } finally {
                    isLoading[type] = false;
                }
            }

            if (myPostsButton) {
                myPostsButton.addEventListener('click', () => {
                    setActiveTab(myPostsButton);
                    loadPosts(myPostsButton.dataset.url, 'my-posts');
                });
                setActiveTab(myPostsButton);
                loadPosts(myPostsButton.dataset.url, 'my-posts');
            }

            if (votedPostsButton) {
                votedPostsButton.addEventListener('click', () => {
                    setActiveTab(votedPostsButton);
                    loadPosts(votedPostsButton.dataset.url, 'voted-posts');
                });
            }
        });
    </script>
@endpush
