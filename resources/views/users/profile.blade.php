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
                {{-- Only show change password if they have a password set --}}
                @if(Auth::user()->password)
                    <a href="{{ route('password.change.form') }}" class="button-link" style="background-color:#6c757d;">Change
                        Password</a>
                @endif
            @endif
            {{-- Add stats here later if needed (e.g., Post Count, Votes Received) --}}
        </div>
    </div>

    <hr>

    <div class="profile-tabs" style="margin-bottom: 1.5em; display: flex; gap: 1em;">
        <button id="load-my-posts" data-url="{{ route('profile.posts.data', $user->username) }}">
            {{ $isOwnProfile ? 'My Posts' : $user->username . "'s Posts" }}
        </button>
        @if ($isOwnProfile)
            <button id="load-voted-posts" data-url="{{ route('profile.voted.data', $user->username) }}">
                Voted Posts
            </button>
        @endif
    </div>

    {{-- Container for User's Posts --}}
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
            const buttons = [myPostsButton, votedPostsButton].filter(btn => btn != null); // Filter out null if not own profile

            let currentPage = {};
            let isLoading = {};
            let hasMorePages = {};

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
                if (isLoading[type]) return; // Prevent multiple simultaneous loads for the same type

                if (!loadMore) {
                    currentPage[type] = 1; // Reset page number for initial load
                    hasMorePages[type] = true; // Assume more pages initially
                    postsContainer.innerHTML = '<p>Loading...</p>'; // Show loading state
                } else {
                    if (!hasMorePages[type]) {
                        console.log('No more pages to load for', type);
                        return; // Stop if no more pages
                    }
                    currentPage[type]++; // Increment page for loading more
                }

                isLoading[type] = true;
                const fetchUrl = `${url}?page=${currentPage[type]}`;

                try {
                    const response = await fetch(fetchUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest', // Important for Laravel request->ajax()
                            'Accept': 'application/json',
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const data = await response.json(); // Expecting { html: '...', hasMorePages: true/false }

                    if (!loadMore) {
                        postsContainer.innerHTML = data.html || '<p>No posts found.</p>'; // Replace content
                    } else {
                        // Remove any existing 'Load More' button before appending new content
                        const existingLoadMoreButton = postsContainer.querySelector('.load-more-button');
                        if (existingLoadMoreButton) {
                            existingLoadMoreButton.remove();
                        }
                        // Append new content
                        postsContainer.insertAdjacentHTML('beforeend', data.html || '');
                    }

                    hasMorePages[type] = data.hasMorePages;

                    // Add 'Load More' button if there are more pages
                    if (hasMorePages[type]) {
                        const loadMoreButton = document.createElement('button');
                        loadMoreButton.textContent = 'Load More';
                        loadMoreButton.classList.add('load-more-button');
                        loadMoreButton.dataset.url = url; // Store url and type for the next load
                        loadMoreButton.dataset.type = type;
                        loadMoreButton.style.marginTop = '1em';
                        loadMoreButton.onclick = () => loadPosts(url, type, true); // Recursive call for next page
                        postsContainer.appendChild(loadMoreButton);
                    } else if (!postsContainer.hasChildNodes() && !loadMore) {
                        postsContainer.innerHTML = '<p>No posts found.</p>';
                    }


                } catch (error) {
                    console.error('Error loading posts:', error);
                    if (!loadMore) {
                        postsContainer.innerHTML = '<p>Error loading posts. Please try again.</p>';
                    } else {
                        // Optionally add error indication near the load more button spot
                    }

                } finally {
                    isLoading[type] = false; // Release lock
                }
            }

            if (myPostsButton) {
                myPostsButton.addEventListener('click', () => {
                    setActiveTab(myPostsButton);
                    loadPosts(myPostsButton.dataset.url, 'my-posts');
                });
                // Optional: Load "My Posts" by default when the page loads
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
