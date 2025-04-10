@php use Illuminate\Support\Facades\Auth;use Illuminate\Support\Str; @endphp
<article class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4"
         id="post-{{ $post->id }}"
         data-option-one-title="{{ $post->option_one_title }}"
         data-option-two-title="{{ $post->option_two_title }}">
    <!-- Header: Profile pic, username and date -->
    <header class="p-4">
        <div class="flex">
            @php
                $profilePic = $post->user->profile_picture
                ? (Str::startsWith($post->user->profile_picture, ['http', 'https'])
                ? $post->user->profile_picture
                : asset('storage/' . $post->user->profile_picture))
                : asset('images/default-pfp.png');

                // Check if user is verified
                $isVerified = in_array($post->user->username, ['goat', 'umarov'])
            @endphp
            <img src="{{ $profilePic }}" alt="{{ $post->user->username }}'s profile picture"
                 class="w-10 h-10 rounded-full border border-gray-300">
            <div class="ml-3">
                <div class="flex items-center">
                    <a href="{{ route('profile.show', $post->user->username) }}"
                       class="font-medium text-gray-800 hover:underline">{{ '@' . $post->user->username }}</a>

                    @if($isVerified)
                        <span class="ml-1" title="Verified Account">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500">{{ $post->created_at->format('Y-m-d H:i:s') }}</p>
            </div>
        </div>
    </header>

    <!-- Horizontal line -->
    <div class="border-b w-full border-gray-200"></div>

    <!-- Question -->
    <div class="pt-4 px-4 font-semibold text-center">
        <p class="text-lg text-gray-800">{{ $post->question }}</p>
    </div>

    <!-- Two images side by side -->
    <div class="grid grid-cols-2 gap-4 p-4 h-52">
        <div class="rounded-md overflow-hidden">
            @if($post->option_one_image)
                <div class="bg-gray-100 flex justify-center">
                    <img src="{{ asset('storage/' . $post->option_one_image) }}" alt="Option 1 Image"
                         class="h-52 object-cover object-center w-full">
                </div>
            @else
                <div class="bg-gray-100 h-full flex items-center justify-center">
                    <div class="bg-gray-200 rounded-full p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-md overflow-hidden">
            @if($post->option_two_image)
                <div class="bg-gray-100 flex justify-center">
                    <img src="{{ asset('storage/' . $post->option_two_image) }}" alt="Option 2 Image"
                         class="h-52 object-cover object-center w-full">
                </div>
            @else
                <div class="bg-gray-100 h-full flex items-center justify-center">
                    <div class="bg-gray-200 rounded-full p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Voting buttons with percentages -->
    <div class="grid grid-cols-2 gap-4 px-4 pb-4">
        @php
            $totalVotes = $post->total_votes;
            $optionOneVotes = $post->option_one_votes;
            $optionTwoVotes = $post->option_two_votes;
            $percentOne = $totalVotes > 0 ? round(($optionOneVotes / $totalVotes) * 100) : 0;
            $percentTwo = $totalVotes > 0 ? round(($optionTwoVotes / $totalVotes) * 100) : 0;
            $hasVoted = Auth::check() && $post->user_vote;
        @endphp

            <!-- Option 1 Button -->
        <button
            class="p-3 text-center rounded-md {{ $hasVoted && $post->user_vote == 'option_one' ? 'bg-blue-800 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' }} {{ !Auth::check() ? 'opacity-75 cursor-not-allowed' : '' }}"
            {{ !Auth::check() ? 'disabled' : '' }}
            onclick="voteForOption('{{ $post->id }}', 'option_one')"
        >
            <p>{{ $post->option_one_title }} {{ $hasVoted ? "($percentOne%)" : "" }}</p>
        </button>

        <!-- Option 2 Button -->
        <button
            class="p-3 text-center rounded-md {{ $hasVoted && $post->user_vote == 'option_two' ? 'bg-blue-800 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' }} {{ !Auth::check() ? 'opacity-75 cursor-not-allowed' : '' }}"
            {{ !Auth::check() ? 'disabled' : '' }}
            onclick="voteForOption('{{ $post->id }}', 'option_two')"
        >
            <p>{{ $post->option_two_title }} {{ $hasVoted ? "($percentTwo%)" : "" }}</p>
        </button>
    </div>

    <!-- Horizontal line -->
    <div class="border-b w-full border-gray-200"></div>

    <!-- Interaction buttons: Comment, Total Votes, Share -->
    <div class="flex justify-between items-center px-8 py-3 text-sm text-gray-600">
        <!-- Comment button with count -->
        <button class="flex flex-col items-center gap-1 cursor-pointer" onclick="toggleComments('{{ $post->id }}')">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <span>{{ $post->comments_count }}</span>
        </button>

        <!-- Total votes counter -->
        <div class="flex flex-col items-center gap-1">
            <span class="text-lg font-semibold">{{ $post->total_votes }}</span>
            <span>Votes</span>
        </div>

        <!-- Share button with count -->
        <button class="flex flex-col items-center gap-1 cursor-pointer" onclick="sharePost('{{ $post->id }}')">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
            </div>
            <span>0</span>
        </button>
    </div>

    <!-- Comments section (hidden by default) -->
    <div id="comments-section-<?php echo e($post->id); ?>" class="hidden">
        <!-- Horizontal line -->
        <div class="border-b border-gray-200"></div>

        <!-- Comment form for authenticated users -->
        <?php if (auth()->guard()->check()): ?>
        <div class="p-4 border-b border-gray-200 comment-form-container">
            <form id="comment-form-<?php echo e($post->id); ?>"
                  onsubmit="submitComment('<?php echo e($post->id); ?>', event)"
                  class="flex flex-col space-y-2">
                    <?php echo csrf_field(); ?>
                <textarea name="content" rows="2" placeholder="Write a comment..." required
                          class="w-full border border-gray-300 rounded-md p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <div class="flex justify-between">
                    <button type="button" onclick="toggleComments('<?php echo e($post->id); ?>')"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm py-1 px-4 rounded-md">Close
                    </button>
                    <button type="submit"
                            class="bg-blue-800 hover:bg-blue-900 text-white text-sm py-1 px-4 rounded-md">Comment
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

            <!-- Comments list with header -->
        <div class="p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Comments</h4>

            <!-- Comments container - this will be populated by JavaScript -->
            <div class="comments-list"></div>

            <!-- Pagination container -->
            <div id="pagination-container-<?php echo e($post->id); ?>" class="mt-4"></div>
        </div>
    </div>

    <!-- Post management options for the post owner -->
    @if (Auth::check() && Auth::id() === $post->user_id && request()->routeIs('profile.show'))
        <div class="flex justify-end space-x-2 p-4 border-t border-gray-200">
            @if($post->total_votes === 0)
                <a href="{{ route('posts.edit', $post) }}"
                   class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm py-1 px-3 rounded-md">Edit</a>
            @else
                <small class="text-gray-500 text-xs self-center">(Cannot edit post with votes)</small>
            @endif
            <form action="{{ route('posts.destroy', $post) }}" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this post? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 text-sm py-1 px-3 rounded-md">
                    Delete
                </button>
            </form>
        </div>
    @endif
</article>

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

    .pagination .page-item.disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .pagination .page-item.disabled .page-link {
        pointer-events: none;
    }

    .comments-list {
        transition: opacity 0.3s ease;
        min-height: 50px;
    }

    #comments-loading {
        opacity: 0;
        animation: fade-in 0.3s ease forwards;
    }

    @keyframes fade-in {
        0% {
            opacity: 0;
        }
        100% {
            opacity: 1;
        }
    }
</style>

<script>
    let currentlyOpenCommentsId = null;

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

    function animateComments(container) {
        const comments = container.querySelectorAll('.comment:not(.visible)');
        comments.forEach((comment, index) => {
            setTimeout(() => {
                comment.classList.add('visible');
            }, 100 * (index + 1));
        });
    }

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

            const existingComments = commentsContainer.querySelectorAll('.comment');
            existingComments.forEach(comment => {
                comment.style.opacity = '0.5';
                comment.style.pointerEvents = 'none';
            });
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

    function createCommentElement(comment, postId) {
        const commentDiv = document.createElement('div');
        commentDiv.className = 'comment mb-3 border-b border-gray-200 pb-3';
        commentDiv.id = 'comment-' + comment.id;

        const profilePic = comment.user.profile_picture
            ? (comment.user.profile_picture.startsWith('http')
                ? comment.user.profile_picture
                : '/storage/' + comment.user.profile_picture)
            : '/images/default-pfp.png';

        // Check if user is verified (same logic as in post header)
        const isVerified = ['goat', 'umarov'].includes(comment.user.username);

        // Verified icon HTML - same SVG used in the post header
        const verifiedIconHTML = isVerified ? `
        <span class="ml-1" title="Verified Account">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                      d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clip-rule="evenodd"/>
            </svg>
        </span>
    ` : '';

        commentDiv.innerHTML = `
    <div class="flex items-center mb-2">
        <img src="${profilePic}" alt="${comment.user.username}'s profile picture" class="w-8 h-8 rounded-full mr-2">
        <div>
            <div class="flex items-center">
                <a href="/@${comment.user.username}" class="text-sm font-medium text-gray-800 hover:underline">${comment.user.username}</a>
                ${verifiedIconHTML}
                <span class="mx-1 text-gray-400">·</span>
                <small class="text-xs text-gray-500" title="${comment.created_at}">${formatTimestamp(comment.created_at)}</small>
            </div>
        </div>
        ${canDeleteComment(comment) ? `
        <div class="ml-auto">
            <form onsubmit="deleteComment('${comment.id}', event)" class="inline">
                <button type="submit" class="text-gray-400 hover:text-red-500 text-xs">Delete</button>
            </form>
        </div>
        ` : ''}
    </div>
    <p class="text-sm text-gray-700 pl-10">${comment.content}</p>
    `;

        return commentDiv;
    }

    function canDeleteComment(comment) {
        const currentUserId = {{ Auth::id() ?? 'null' }};
        return currentUserId === comment.user_id || currentUserId === comment.post.user_id;
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
        } else {
            const disabledPrev = document.createElement('div');
            disabledPrev.className = 'page-item disabled';
            disabledPrev.innerHTML = '<span class="page-link">&laquo;</span>';
            pagination.appendChild(disabledPrev);
        }

        const startPage = Math.max(1, comments.current_page - 2);
        const endPage = Math.min(comments.last_page, comments.current_page + 2);

        if (startPage > 1) {
            pagination.appendChild(createPageLink('1', 1, postId));
            if (startPage > 2) {
                const ellipsis = document.createElement('div');
                ellipsis.className = 'page-item';
                ellipsis.innerHTML = '<span class="page-link">...</span>';
                pagination.appendChild(ellipsis);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const pageLink = createPageLink(i.toString(), i, postId, i === comments.current_page);
            pagination.appendChild(pageLink);
        }

        if (endPage < comments.last_page) {
            if (endPage < comments.last_page - 1) {
                const ellipsis = document.createElement('div');
                ellipsis.className = 'page-item';
                ellipsis.innerHTML = '<span class="page-link">...</span>';
                pagination.appendChild(ellipsis);
            }
            pagination.appendChild(createPageLink(comments.last_page.toString(), comments.last_page, postId));
        }

        if (comments.current_page < comments.last_page) {
            const nextLink = createPageLink('&raquo;', comments.current_page + 1, postId);
            pagination.appendChild(nextLink);
        } else {
            const disabledNext = document.createElement('div');
            disabledNext.className = 'page-item disabled';
            disabledNext.innerHTML = '<span class="page-link">&raquo;</span>';
            pagination.appendChild(disabledNext);
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
            if (pageItem.classList.contains('active')) {
                return;
            }

            const commentsSection = document.getElementById(`comments-section-${postId}`);
            const scrollPosition = commentsSection.scrollTop;

            const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
            if (paginationContainer) {
                const overlay = document.createElement('div');
                overlay.style.position = 'absolute';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.right = '0';
                overlay.style.bottom = '0';
                overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.5)';
                overlay.style.zIndex = '10';
                overlay.style.transition = 'opacity 0.2s ease';

                const commentsListContainer = commentsSection.querySelector('.comments-list');
                if (commentsListContainer && getComputedStyle(commentsListContainer).position === 'static') {
                    commentsListContainer.style.position = 'relative';
                }

                commentsListContainer.appendChild(overlay);

                setTimeout(() => {
                    loadComments(postId, page);

                    setTimeout(() => {
                        overlay.style.opacity = '0';
                        setTimeout(() => {
                            overlay.remove();
                            commentsSection.scrollTop = scrollPosition;
                        }, 200);
                    }, 300);
                }, 50);
            } else {
                loadComments(postId, page);
            }
        };

        pageItem.appendChild(link);
        return pageItem;
    }

    function submitComment(postId, event) {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const content = form.elements.content.value;

        if (!content.trim()) {
            alert('Comment cannot be empty');
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
                    alert('Error: ' + Object.values(data.errors).join('\n'));
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

                const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                if (paginationContainer && commentsSection.dataset.currentPage !== '1') {
                    fetch(`/posts/${postId}/comments?page=1`)
                        .then(response => response.json())
                        .then(pageData => {
                            renderPagination(pageData.comments, postId, paginationContainer);
                        });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitButton.disabled = false;
                submitButton.innerHTML = 'Comment';
                alert('Failed to add comment. Please try again.');
            });
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

        setTimeout(() => {
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
                        alert(data.error);
                        commentElement.style.opacity = '1';
                        commentElement.style.transform = 'translateY(0)';
                        return;
                    }

                    commentElement.addEventListener('transitionend', () => {
                        commentElement.remove();
                    }, {once: true});

                    const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                    if (commentCountElement) {
                        const currentCount = parseInt(commentCountElement.textContent);
                        commentCountElement.textContent = currentCount - 1;
                    }

                    const commentsSection = document.getElementById(`comments-section-${postId}`);
                    const commentsContainer = commentsSection.querySelector('.comments-list');
                    const remainingComments = commentsContainer.querySelectorAll('.comment');

                    if (remainingComments.length <= 1) {
                        const currentPage = parseInt(commentsSection.dataset.currentPage) || 1;
                        if (currentPage > 1) {
                            setTimeout(() => {
                                loadComments(postId, currentPage - 1);
                            }, 300);
                        } else {
                            setTimeout(() => {
                                commentsContainer.innerHTML = '<p class="text-sm text-gray-500 text-center">No comments yet. Be the first to comment!</p>';

                                const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                                if (paginationContainer) {
                                    paginationContainer.innerHTML = '';
                                }
                            }, 300);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    commentElement.style.opacity = '1';
                    commentElement.style.transform = 'translateY(0)';
                    alert('Failed to delete comment. Please try again.');
                });
        }, 300);
    }

    function voteForOption(postId, option) {
        // Don't proceed if user is not logged in
        if (!{{ Auth::check() ? 'true' : 'false' }}) {
            alert('You need to be logged in to vote.');
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(`/posts/${postId}/vote`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({option: option})
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 409) {
                        return response.json().then(data => {
                            throw new Error(data.error || 'You have already voted on this post.');
                        });
                    }
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const postElement = document.getElementById(`post-${postId}`);
                const optionOneBtn = postElement.querySelector('.grid.grid-cols-2.gap-4.px-4.pb-4 button:first-child');
                const optionTwoBtn = postElement.querySelector('.grid.grid-cols-2.gap-4.px-4.pb-4 button:last-child');

                const percentOne = data.total_votes > 0 ? Math.round((data.option_one_votes / data.total_votes) * 100) : 0;
                const percentTwo = data.total_votes > 0 ? Math.round((data.option_two_votes / data.total_votes) * 100) : 0;

                optionOneBtn.className = option === 'option_one' ?
                    'p-3 text-center rounded-md bg-blue-800 text-white' :
                    'p-3 text-center rounded-md bg-white border border-gray-300 hover:bg-gray-50';

                optionTwoBtn.className = option === 'option_two' ?
                    'p-3 text-center rounded-md bg-blue-800 text-white' :
                    'p-3 text-center rounded-md bg-white border border-gray-300 hover:bg-gray-50';

                const optionOneTitle = postElement.getAttribute('data-option-one-title');
                const optionTwoTitle = postElement.getAttribute('data-option-two-title');

                optionOneBtn.querySelector('p').textContent = `${optionOneTitle} (${percentOne}%)`;
                optionTwoBtn.querySelector('p').textContent = `${optionTwoTitle} (${percentTwo}%)`;

                const votesCountElement = postElement.querySelector('.flex.justify-between.items-center.px-8.py-3 .flex.flex-col.items-center.gap-1 span:first-child');
                if (votesCountElement) {
                    votesCountElement.textContent = data.total_votes;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Failed to register vote. Please try again.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
        commentsSections.forEach(section => {
            section.classList.add('comments-section');
            section.classList.add('hidden');

            const formContainer = section.querySelector('form')?.closest('div');
            if (formContainer) {
                formContainer.classList.add('comment-form-container');
            }
        });
    });
</script>
