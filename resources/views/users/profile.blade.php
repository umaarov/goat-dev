@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', e($user->username) . "'s Profile")

@section('content')
    <div class="profile-header">
        @php
            $profilePic = $user->profile_picture
                ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                    ? $user->profile_picture
                    : asset('storage/' . $user->profile_picture))
                : asset('images/default-pfp.png');
        @endphp
        <img src="{{ $profilePic }}" alt="{{ $user->username }}'s profile picture">
        <div>
            <h2>{{ $user->username }}</h2>
            @if($user->first_name)
                <p>{{ $user->first_name }} {{ $user->last_name }}</p>
            @endif
            <p><small>Joined: {{ $user->created_at->format('M d, Y') }}</small></p>

            @if ($isOwnProfile)
                <a href="{{ route('profile.edit') }}" class="button-link">Edit Profile</a>
                @if(Auth::user()->password)
                    <a href="{{ route('password.change.form') }}" class="button-link">Change Password</a>
                @endif
            @endif
        </div>
    </div>

    <hr>

    <div class="profile-tabs">
        <button id="load-my-posts" data-url="{{ route('profile.posts.data', $user->username) }}">
            {{ $isOwnProfile ? 'My Posts' : $user->username . "'s Posts" }}
        </button>
        @if ($isOwnProfile)
            <button id="load-voted-posts" data-url="{{ route('profile.voted.data', $user->username) }}">
                Voted Posts
            </button>
        @endif
    </div>

    <div id="posts-container">
        <p>Click a button above to load posts.</p>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const postsContainer = document.getElementById('posts-container');
            const myPostsButton = document.getElementById('load-my-posts');
            const votedPostsButton = document.getElementById('load-voted-posts');
            const buttons = [myPostsButton, votedPostsButton].filter(btn => btn != null);

            let currentPage = {};
            let isLoading = {};
            let hasMorePages = {};

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function setActiveTab(activeButton) {
                buttons.forEach(btn => {
                    btn.style.fontWeight = 'normal';
                    btn.classList.remove('active');
                });
                if (activeButton) {
                    activeButton.style.fontWeight = 'bold';
                    activeButton.classList.add('active');
                }
            }

            async function loadPosts(url, type, loadMore = false) {
                if (isLoading[type]) return;

                if (!loadMore) {
                    currentPage[type] = 1;
                    hasMorePages[type] = true;
                    postsContainer.innerHTML = '<p>Loading...</p>';
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
                        postsContainer.innerHTML = data.html || '<p>No posts found.</p>';
                    } else {
                        const existingLoadMoreButton = postsContainer.querySelector('.load-more-button');
                        if (existingLoadMoreButton) {
                            existingLoadMoreButton.remove();
                        }
                        postsContainer.insertAdjacentHTML('beforeend', data.html || '');
                    }

                    hasMorePages[type] = data.hasMorePages;

                    if (hasMorePages[type]) {
                        const loadMoreButton = document.createElement('button');
                        loadMoreButton.textContent = 'Load More';
                        loadMoreButton.classList.add('load-more-button');
                        loadMoreButton.dataset.url = url;
                        loadMoreButton.dataset.type = type;
                        loadMoreButton.style.marginTop = '1em';
                        loadMoreButton.onclick = () => loadPosts(url, type, true);
                        postsContainer.appendChild(loadMoreButton);
                    } else if (postsContainer.children.length === 0 && !loadMore) {
                        postsContainer.innerHTML = '<p>No posts found.</p>';
                    }

                } catch (error) {
                    console.error('Error loading posts:', error);
                    postsContainer.innerHTML = '<p>Error loading posts. Please try again.</p>';
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
