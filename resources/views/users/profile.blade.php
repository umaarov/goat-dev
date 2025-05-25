@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', e($user->username) . "'s Profile")
@section('meta_description', 'View the profile, created polls, and activity of ' . $user->username . ' on GOAT. Join the discussion!')

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

                        // Determine the main display name
                        $displayName = ($user->first_name || $user->last_name) ? trim($user->first_name . ' ' . $user->last_name) : "@".$user->username;
                        // Determine if @username should be shown as a sub-line
                        $showSubUsername = ($user->first_name || $user->last_name);
                    @endphp
                    <img src="{{ $profilePic }}" alt="{{ $user->username }}'s profile picture"
                         class="h-24 w-24 rounded-full object-cover border border-gray-200 cursor-pointer zoomable-image flex-shrink-0"
                         {{-- Added flex-shrink-0 --}}
                         data-full-src="{{ $profilePic }}">
                    <div class="ml-6 flex-1">
                        {{-- Name / Username --}}
                        <div class="flex items-center">
                            <h2 class="text-2xl font-semibold text-gray-800">{{ $displayName }}</h2>
                            @if($isVerified)
                                <span class="ml-1.5" title="Verified Account"> {{-- Adjusted margin slightly --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </span>
                            @endif
                        </div>

                        @if($showSubUsername)
                            <p class="text-gray-600 text-sm">{{ "@".$user->username }}</p>
                        @endif

                        <p class="text-gray-500 text-xs mt-[4px]">Joined: {{ $user->created_at->format('M d, Y') }}</p>

                        {{-- Stats Section --}}
                        <div class="mt-2 flex space-x-5 text-sm">
                            <div>
                                <span class="font-semibold text-gray-800">{{ number_format($user->posts_count) }}</span>
                                <span class="text-gray-500">Posts</span>
                            </div>
                            <div>
                                <span
                                    class="font-semibold text-gray-800">{{ number_format($totalVotesOnUserPosts) }}</span>
                                <span class="text-gray-500">Votes Collected</span>
                            </div>
                        </div>

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

            {{-- Voted Posts tab --}}
            @if ($isOwnProfile || $user->show_voted_posts_publicly)
                <button id="load-voted-posts" data-url="{{ route('profile.voted.data', $user->username) }}"
                        class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                    Voted Posts
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-transparent transition-all duration-300"
                          id="voted-posts-indicator"></span>
                </button>
            @endif
        </div>
    </div>

    <!-- Posts container -->
    <div id="posts-container" class="space-y-4">
        <p class="text-gray-500 text-center py-8">Loading posts...</p>
    </div>
    </div>
@endsection

<style>
    .comments-section {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease-in-out, opacity 0.3s ease-in-out;
        opacity: 0;
    }

    .comments-section.active {
        max-height: 2000px;
        opacity: 1;
    }

    .comment-form-container {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .comments-section.active .comment-form-container {
        opacity: 1;
        transform: translateY(0);
    }

    .comment {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 12px;
        margin-bottom: 12px;
    }

    .comment.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 4px;
        margin-top: 16px;
        transition: opacity 0.3s ease;
    }

    .pagination .page-item {
        margin: 0;
    }

    .pagination .page-link {
        display: inline-block;
        padding: 5px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        color: #4a5568;
        text-decoration: none;
        transition: background-color 0.2s ease;
        cursor: pointer;
    }

    .pagination .page-link:hover {
        background-color: #edf2f7;
    }

    .pagination .page-item.active .page-link {
        background-color: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    .zoomable-image {
        cursor: pointer;
    }
</style>

@push('scripts')
    <script>
        window.isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('scrollToPost'))
            scrollToPost({{ session('scrollToPost') }});
            @endif
        });

        function scrollToPost(postId) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            setTimeout(() => {
                window.scrollTo({
                    top: postElement.offsetTop - 100,
                    behavior: 'smooth'
                });

                postElement.classList.add('highlight-post');
                setTimeout(() => {
                    postElement.classList.remove('highlight-post');
                }, 1500);
            }, 300);
        }

        function sharePost(postId) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            const question = postElement.querySelector('.pt-4.px-4.font-semibold.text-center p').textContent;
            const slug = question.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-')
                .substring(0, 60);

            const shareUrl = `${window.location.origin}/p/${postId}/${slug}`;

            if (navigator.share) {
                navigator.share({
                    title: question,
                    url: shareUrl
                }).catch(error => {
                    console.log('Error sharing:', error);
                    fallbackShare(shareUrl);
                });
            } else {
                fallbackShare(shareUrl);
            }

            updateShareCount(postId);
        }

        function fallbackShare(url) {
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);

            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);

            showToast('Link copied to clipboard!')
        }

        function updateShareCount(postId) {
            const shareCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:last-child span`);
            if (shareCountElement) {
                const currentCount = parseInt(shareCountElement.textContent);
                const newCount = isNaN(currentCount) ? 1 : currentCount + 1;
                shareCountElement.textContent = newCount;

                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                fetch(`/posts/${postId}/share`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                }).catch(error => {
                    console.error('Error updating share count:', error);
                });
            }
        }

        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) {
                return 'Just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            } else {
                return date.toLocaleDateString();
            }
        }

        function initializePostInteractions() {
            const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
            commentsSections.forEach(section => {
                if (!section.classList.contains('comments-section')) {
                    section.classList.add('comments-section');
                    section.classList.add('hidden');

                    const formContainer = section.querySelector('form')?.closest('div');
                    if (formContainer) {
                        formContainer.classList.add('comment-form-container');
                    }
                }
            });

            // Initialize share buttons
            const shareButtons = document.querySelectorAll('[onclick^="sharePost"]');
            shareButtons.forEach(button => {
                const postId = button.getAttribute('onclick').match(/'([^']+)'/)[1];
                button.onclick = () => sharePost(postId);
            });

            // Initialize comment toggle buttons
            const commentButtons = document.querySelectorAll('[onclick^="toggleComments"]');
            commentButtons.forEach(button => {
                const postId = button.getAttribute('onclick').match(/'([^']+)'/)[1];
                button.onclick = () => toggleComments(postId);
            });

            // Initialize voting buttons
            const voteButtons = document.querySelectorAll('[onclick^="voteForOption"]');
            voteButtons.forEach(button => {
                const match = button.getAttribute('onclick').match(/voteForOption\('([^']+)',\s*'([^']+)'\)/);
                if (match) {
                    const postId = match[1];
                    const option = match[2];
                    button.onclick = () => voteForOption(postId, option);
                }
            });

            // Initialize comment forms
            const commentForms = document.querySelectorAll('[onsubmit^="submitComment"]');
            commentForms.forEach(form => {
                const match = form.getAttribute('onsubmit').match(/submitComment\('([^']+)',\s*event\)/);
                if (match) {
                    const postId = match[1];
                    form.onsubmit = (event) => submitComment(postId, event);
                }
            });
        }

        function toggleComments(postId) {
            const clickedCommentsSection = document.getElementById(`comments-section-${postId}`);

            if (currentlyOpenCommentsId && currentlyOpenCommentsId !== postId) {
                const previousCommentsSection = document.getElementById(`comments-section-${currentlyOpenCommentsId}`);
                if (previousCommentsSection) {
                    previousCommentsSection.classList.remove('active');
                    previousCommentsSection.classList.add('hidden');
                    currentlyOpenCommentsId = null;
                }
            }

            if (clickedCommentsSection.classList.contains('hidden')) {
                clickedCommentsSection.classList.remove('hidden');

                setTimeout(() => {
                    clickedCommentsSection.classList.add('active');
                    currentlyOpenCommentsId = postId;

                    if (!clickedCommentsSection.dataset.loaded) {
                        loadComments(postId, 1);
                    }
                }, 10);
            } else {
                clickedCommentsSection.classList.remove('active');

                setTimeout(() => {
                    clickedCommentsSection.classList.add('hidden');
                    currentlyOpenCommentsId = null;
                }, 500);
            }
        }

        let currentlyOpenCommentsId = null;

        function loadComments(postId, page) {
            const commentsSection = document.getElementById(`comments-section-${postId}`);
            const commentsContainer = commentsSection.querySelector('.comments-list');

            if (!commentsContainer) {
                console.error('Comments container not found');
                return;
            }

            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'text-center py-4';
            loadingIndicator.id = 'comments-loading';
            loadingIndicator.innerHTML = '<div class="inline-block animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div>';

            if (!commentsSection.dataset.loaded || page === 1) {
                commentsContainer.innerHTML = '';
                commentsContainer.appendChild(loadingIndicator);
            } else {
                commentsContainer.appendChild(loadingIndicator);
            }

            fetch(`/posts/${postId}/comments?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    const loadingElement = commentsContainer.querySelector('#comments-loading');
                    if (loadingElement) {
                        loadingElement.remove();
                    }

                    commentsContainer.innerHTML = '';

                    if (data.comments.data.length === 0) {
                        commentsContainer.innerHTML = '<p class="text-sm text-gray-500 text-center">No comments yet. Be the first to comment!</p>';
                        return;
                    }

                    data.comments.data.forEach(comment => {
                        const commentDiv = createCommentElement(comment, postId);
                        commentsContainer.appendChild(commentDiv);
                    });

                    animateComments(commentsContainer);

                    const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                    if (paginationContainer) {
                        renderPagination(data.comments, postId, paginationContainer);
                    }

                    commentsSection.dataset.loaded = "true";
                    commentsSection.dataset.currentPage = page;
                })
                .catch(error => {
                    console.error('Error:', error);
                    commentsContainer.innerHTML = '<p class="text-red-500 text-center">Failed to load comments. Please try again.</p>';
                });
        }

        async function voteForOption(postId, option) {
            if (!{{ Auth::check() ? 'true' : 'false' }}) {
                if (window.showToast) window.showToast('You need to be logged in to vote.', 'warning');
                return;
            }

            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) {
                console.error(`Post element with ID post-${postId} not found.`);
                return;
            }

            const clickedButton = postElement.querySelector(`button.vote-button[data-option="${option}"]`);
            const otherButtonOption = option === 'option_one' ? 'option_two' : 'option_one';
            const otherButton = postElement.querySelector(`button.vote-button[data-option="${otherButtonOption}"]`);

            if (!clickedButton || !otherButton) {
                console.error('Vote buttons not found for post-' + postId);
                return;
            }

            if (clickedButton.disabled) {
                return;
            }

            const knownUserVote = postElement.dataset.userVote;
            if (knownUserVote && (knownUserVote === 'option_one' || knownUserVote === 'option_two')) {
                const currentVoteData = {
                    user_vote: knownUserVote,
                    option_one_votes: parseInt(postElement.dataset.optionOneVotes, 10),
                    option_two_votes: parseInt(postElement.dataset.optionTwoVotes, 10),
                    total_votes: parseInt(postElement.dataset.optionOneVotes, 10) + parseInt(postElement.dataset.optionTwoVotes, 10),
                    message: "You have already voted on this post."
                };
                updateVoteUI(postId, currentVoteData.user_vote, currentVoteData);
                if (window.showToast) window.showToast(currentVoteData.message, 'info');
                return;
            }

            const originalOptionOneVotes = parseInt(postElement.dataset.optionOneVotes, 10);
            const originalOptionTwoVotes = parseInt(postElement.dataset.optionTwoVotes, 10);
            const originalUserVoteState = postElement.dataset.userVote || '';

            const originalClickedButtonClasses = Array.from(clickedButton.classList);
            const originalOtherButtonClasses = Array.from(otherButton.classList);

            const totalVotesDisplayElement = postElement.querySelector('.flex.justify-between.items-center .flex.flex-col.items-center.gap-1 span.text-lg.font-semibold');
            const originalTotalVotesText = totalVotesDisplayElement ? totalVotesDisplayElement.textContent : (originalOptionOneVotes + originalOptionTwoVotes).toString();


            const highlightClasses = ['bg-blue-800', 'text-white'];
            const defaultBgClasses = ['bg-white', 'border', 'border-gray-300', 'hover:bg-gray-50'];

            clickedButton.classList.remove(...defaultBgClasses, ...highlightClasses);
            clickedButton.classList.add(...highlightClasses);

            otherButton.classList.remove(...highlightClasses, ...defaultBgClasses);
            otherButton.classList.add(...defaultBgClasses);

            clickedButton.classList.add('voting-in-progress');

            clickedButton.disabled = true;
            otherButton.disabled = true;

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            let responseOk = false;

            try {
                const response = await fetch(`/posts/${postId}/vote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({option: option})
                });

                const responseData = await response.json();
                responseOk = response.ok;

                if (responseOk) { // HTTP 200-299
                    updateVoteUI(postId, responseData.user_vote, responseData);
                } else { // HTTP errors 409, 422, 500
                    const errorMessage = responseData.message || responseData.error || (responseData.errors ? Object.values(responseData.errors).join(', ') : `Vote failed (HTTP ${response.status})`);
                    if (window.showToast) {
                        window.showToast(errorMessage, response.status === 409 ? 'info' : 'error');
                    }

                    if (response.status === 409 && responseData.user_vote && typeof responseData.option_one_votes !== 'undefined') {
                        updateVoteUI(postId, responseData.user_vote, responseData);
                    } else {
                        clickedButton.setAttribute('class', originalClickedButtonClasses.join(' '));
                        otherButton.setAttribute('class', originalOtherButtonClasses.join(' '));
                        postElement.dataset.userVote = originalUserVoteState;
                        postElement.dataset.optionOneVotes = originalOptionOneVotes.toString();
                        postElement.dataset.optionTwoVotes = originalOptionTwoVotes.toString();
                        if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;
                    }
                }
            } catch (error) {
                console.error('Error voting (catch):', error);
                if (window.showToast) {
                    window.showToast('Failed to register vote. Please check your connection.', 'error');
                }
                clickedButton.setAttribute('class', originalClickedButtonClasses.join(' '));
                otherButton.setAttribute('class', originalOtherButtonClasses.join(' '));
                postElement.dataset.userVote = originalUserVoteState;
                postElement.dataset.optionOneVotes = originalOptionOneVotes.toString();
                postElement.dataset.optionTwoVotes = originalOptionTwoVotes.toString();
                if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;

            } finally {
                clickedButton.classList.remove('voting-in-progress');
                clickedButton.disabled = false;
                otherButton.disabled = false;
            }
        }

        function updateVoteUI(postId, userVotedOption, voteData) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) {
                console.error(`Post element with ID post-${postId} not found.`);
                return;
            }

            postElement.dataset.userVote = userVotedOption;

            postElement.dataset.optionOneVotes = voteData.option_one_votes;
            postElement.dataset.optionTwoVotes = voteData.option_two_votes;

            const totalVotesDisplayElement = postElement.querySelector('.flex.justify-between.items-center .flex.flex-col.items-center.gap-1 span.text-lg.font-semibold');
            if (totalVotesDisplayElement) {
                totalVotesDisplayElement.textContent = voteData.total_votes;
            }

            const optionOneButton = postElement.querySelector('button.vote-button[data-option="option_one"]');
            const optionTwoButton = postElement.querySelector('button.vote-button[data-option="option_two"]');

            if (optionOneButton && optionTwoButton) {
                const optionOneTitle = postElement.dataset.optionOneTitle || 'Option 1';
                const optionTwoTitle = postElement.dataset.optionTwoTitle || 'Option 2';

                const totalVotes = parseInt(voteData.total_votes, 10);
                const optionOneVotes = parseInt(voteData.option_one_votes, 10);
                const optionTwoVotes = parseInt(voteData.option_two_votes, 10);

                const percentOne = totalVotes > 0 ? Math.round((optionOneVotes / totalVotes) * 100) : 0;
                const percentTwo = totalVotes > 0 ? Math.round((optionTwoVotes / totalVotes) * 100) : 0;

                const optionOneTextElement = optionOneButton.querySelector('.button-text-truncate');
                if (optionOneTextElement) {
                    optionOneTextElement.textContent = `${optionOneTitle} (${percentOne}%)`;
                }
                const optionTwoTextElement = optionTwoButton.querySelector('.button-text-truncate');
                if (optionTwoTextElement) {
                    optionTwoTextElement.textContent = `${optionTwoTitle} (${percentTwo}%)`;
                }

                const highlightClasses = ['bg-blue-800', 'text-white'];
                const defaultClasses = ['bg-white', 'border', 'border-gray-300', 'hover:bg-gray-50'];
                const noHoverDefaultClasses = ['bg-white', 'border', 'border-gray-300'];

                [optionOneButton, optionTwoButton].forEach(button => {
                    button.classList.remove(...highlightClasses, ...defaultClasses, ...noHoverDefaultClasses);
                    button.classList.remove('bg-blue-800', 'text-white', 'bg-white', 'border', 'border-gray-300', 'hover:bg-gray-50');
                });

                if (userVotedOption === 'option_one') {
                    optionOneButton.classList.add(...highlightClasses);
                    optionTwoButton.classList.add(...noHoverDefaultClasses);
                } else if (userVotedOption === 'option_two') {
                    optionTwoButton.classList.add(...highlightClasses);
                    optionOneButton.classList.add(...noHoverDefaultClasses);
                } else {
                    optionOneButton.classList.add(...defaultClasses);
                    optionTwoButton.classList.add(...defaultClasses);
                }

                optionOneButton.dataset.tooltipShowCount = "true";
                optionTwoButton.dataset.tooltipShowCount = "true";
            }

            if (window.showToast && voteData.message && !voteData.message.toLowerCase().includes('already voted')) {
                window.showToast(voteData.message, 'success');
            } else if (window.showToast && voteData.message && voteData.message.toLowerCase().includes('already voted')) {
                window.showToast(voteData.message, 'info');
            }
        }

        function deleteComment(commentId, event) {
            event.preventDefault();

            if (!confirm('Delete this comment?')) {
                return;
            }

            const commentElement = document.getElementById('comment-' + commentId);
            if (!commentElement) {
                console.error('Comment element not found');
                return;
            }

            const postId = commentElement.closest('[id^="comments-section-"]').id.split('-')[2];

            commentElement.style.transition = 'opacity 0.3s, transform 0.3s';
            commentElement.style.opacity = '0';
            commentElement.style.transform = 'translateY(10px)';

            const url = `/comments/${commentId}`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showToast(data.error);
                        commentElement.style.opacity = '1';
                        commentElement.style.transform = 'translateY(0)';
                        return;
                    }

                    // Add a definite transitionend listener
                    const handleTransitionEnd = function () {
                        commentElement.remove();
                        commentElement.removeEventListener('transitionend', handleTransitionEnd);
                    };
                    commentElement.addEventListener('transitionend', handleTransitionEnd);

                    const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                    if (commentCountElement) {
                        const currentCount = parseInt(commentCountElement.textContent);
                        commentCountElement.textContent = currentCount - 1;
                    }

                    const commentsSection = document.getElementById(`comments-section-${postId}`);
                    const commentsContainer = commentsSection.querySelector('.comments-list');
                    const remainingComments = commentsContainer.querySelectorAll('.comment:not(#comment-${commentId})');

                    if (remainingComments.length === 0) {
                        const currentPage = parseInt(commentsSection.dataset.currentPage) || 1;
                        if (currentPage > 1) {
                            setTimeout(() => {
                                loadComments(postId, currentPage - 1);
                            }, 300);
                        } else {
                            setTimeout(() => {
                                if (commentsContainer.querySelectorAll('.comment').length === 0) {
                                    commentsContainer.innerHTML = '<p class="text-sm text-gray-500 text-center">No comments yet. Be the first to comment!</p>';

                                    const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                                    if (paginationContainer) {
                                        paginationContainer.innerHTML = '';
                                    }
                                }
                            }, 300);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    commentElement.style.opacity = '1';
                    commentElement.style.transform = 'translateY(0)';
                    // alert('Failed to delete comment. Please try again.');
                });
        }

        function submitComment(postId, event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const content = form.elements.content.value;

            if (!content.trim()) {
                showToast('Comment cannot be empty');
                return;
            }

            submitButton.disabled = true;
            submitButton.innerHTML = '<div class="inline-block animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>';

            const url = `/posts/${postId}/comments`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({content: content})
            })
                .then(response => response.json())
                .then(data => {
                    form.elements.content.value = '';

                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Comment';

                    if (data.errors) {
                        showToast('Error: ' + Object.values(data.errors).join('\n'));
                        return;
                    }

                    const currentUserId = {{ Auth::id() ?? 'null' }};
                    const currentUsername = '{{ Auth::check() ? Auth::user()->username : "" }}';
                    const currentUserProfilePic = '{{ Auth::check() ? (Auth::user()->profile_picture ? (Str::startsWith(Auth::user()->profile_picture, ["http", "https"]) ? Auth::user()->profile_picture : asset("storage/" . Auth::user()->profile_picture)) : asset("images/default-pfp.png")) : "" }}';

                    const newComment = {
                        id: data.comment.id,
                        content: data.comment.content,
                        created_at: data.comment.created_at,
                        user: {
                            id: currentUserId,
                            username: currentUsername,
                            profile_picture: currentUserProfilePic
                        },
                        post: {
                            user_id: data.comment.post.user_id
                        },
                        user_id: currentUserId
                    };

                    const commentsSection = document.getElementById(`comments-section-${postId}`);
                    const commentsContainer = commentsSection.querySelector('.comments-list');

                    const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                    if (noCommentsMessage) {
                        commentsContainer.innerHTML = '';
                    }

                    const commentElement = createCommentElement(newComment, postId);
                    if (commentsContainer.firstChild) {
                        commentsContainer.insertBefore(commentElement, commentsContainer.firstChild);
                    } else {
                        commentsContainer.appendChild(commentElement);
                    }

                    setTimeout(() => {
                        commentElement.classList.add('visible');
                    }, 10);

                    const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                    if (commentCountElement) {
                        const currentCount = parseInt(commentCountElement.textContent);
                        commentCountElement.textContent = currentCount + 1;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Comment';
                    showToast('Failed to add comment. Please try again.');
                });
        }

        function createCommentElement(comment, postId) {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment mb-3 border-b border-gray-200 pb-3';
            commentDiv.id = 'comment-' + comment.id;

            const profilePic = comment.user.profile_picture
                ? (comment.user.profile_picture.startsWith('http')
                    ? comment.user.profile_picture
                    : '/storage/' + comment.user.profile_picture)
                : '/images/default-pfp.png';

            const isVerified = ['goat', 'umarov'].includes(comment.user.username);
            const verifiedIconHTML = isVerified ? `
                <span class="ml-1" title="Verified Account">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </span>` : '';

            commentDiv.innerHTML = `
            <div class="flex items-start mb-2">
            <img src="${profilePic}" alt="${comment.user.username}'s profile picture"
                     class="w-8 h-8 rounded-full mr-2 mt-1 cursor-pointer zoomable-image"
            data-full-src="${profilePic}">
            <div class="flex-1">
                <div class="flex items-center">
                    <a href="/@${comment.user.username}" class="text-sm font-medium text-gray-800 hover:underline">${comment.user.username}</a>
                        ${verifiedIconHTML}
                        <span class="mx-1 text-gray-400 text-xs">·</span>
                        <small class="text-xs text-gray-500" title="${comment.created_at}">${formatTimestamp(comment.created_at)}</small>
                    </div>
                    <p class="text-sm text-gray-700 break-words">${comment.content}</p>
            </div>
${canDeleteComment(comment) ? `
                <div class="ml-auto pl-2">
                    <form onsubmit="deleteComment('${comment.id}', event)" class="inline">
                        <button type="submit" class="text-gray-400 hover:text-red-500 text-xs p-1" title="Delete comment">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </form>
                </div>
                ` : ''}
            </div>
            `;
            return commentDiv;
        }

        function canDeleteComment(comment) {
            const currentUserId = {{ Auth::id() ?? 'null' }};
            return currentUserId === comment.user_id || currentUserId === comment.post.user_id;
        }


        function animateComments(container) {
            const comments = container.querySelectorAll('.comment:not(.visible)');
            comments.forEach((comment, index) => {
                setTimeout(() => {
                    comment.classList.add('visible');
                }, 100 * (index + 1));
            });
        }

        function renderPagination(comments, postId, container) {
            container.innerHTML = '';

            if (comments.last_page <= 1) {
                return;
            }

            const pagination = document.createElement('div');
            pagination.className = 'pagination';

            if (comments.current_page > 1) {
                const prevLink = createPageLink('&laquo;', comments.current_page - 1, postId);
                pagination.appendChild(prevLink);
            }

            for (let i = 1; i <= comments.last_page; i++) {
                const pageLink = createPageLink(i.toString(), i, postId, i === comments.current_page);
                pagination.appendChild(pageLink);
            }

            if (comments.current_page < comments.last_page) {
                const nextLink = createPageLink('&raquo;', comments.current_page + 1, postId);
                pagination.appendChild(nextLink);
            }

            container.appendChild(pagination);
        }

        function createPageLink(text, page, postId, isActive = false) {
            const pageItem = document.createElement('div');
            pageItem.className = `page-item ${isActive ? 'active' : ''}`;

            const link = document.createElement('a');
            link.className = 'page-link';
            link.href = 'javascript:void(0)';
            link.innerHTML = text;
            link.onclick = (e) => {
                e.preventDefault();
                loadComments(postId, page);
            };

            pageItem.appendChild(link);
            return pageItem;
        }

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

                    initializePostInteractions();

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
                    const isLoggedIn = window.isLoggedIn === true || window.isLoggedIn === 'true';

                    if (!isLoggedIn) {
                        postsContainer.innerHTML = `
            <div class="text-center py-4">
                <p class="text-sm text-gray-500">
                    Please <a class="text-blue-800 hover:underline" href="/login">log in</a> to see {{$user->username}}'s post.
                </p>
            </div>`;
                    } else {
                        postsContainer.innerHTML = `
            <p class="text-red-500 text-center py-8">
                Error loading posts. Please try again.
            </p>`;
                    }

                    console.error('Error loading posts:', error);
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

@section('structured_data')
    <script type="application/ld+json">
        {
        "@context": "https://schema.org",
        "@type": "Person",
        "name": "{{ $user->username }}",
        "url": "{{ route('profile.show', ['username' => $user->username]) }}",
        @if($user->profile_picture_url && !str_contains($user->profile_picture_url, 'initial_'))
            "image": "{{ $user->profile_picture_url }}",
        @endif
        "description": "View {{ $user->username }}'s profile and polls on GOAT.",
        "mainEntityOfPage": {
        "@type": "ProfilePage",
        "@id": "{{ route('profile.show', ['username' => $user->username]) }}"
  }
}
    </script>
@endsection
