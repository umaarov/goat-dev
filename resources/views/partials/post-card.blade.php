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
            @endphp
            <img src="{{ $profilePic }}" alt="{{ $post->user->username }}'s profile picture"
                 class="w-10 h-10 rounded-full border border-gray-300">
            <div class="ml-3">
                <a href="{{ route('profile.show', $post->user->username) }}"
                   class="font-medium text-gray-800 hover:underline">{{ '@' . $post->user->username }}</a>
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
        <button class="flex flex-col items-center gap-1" onclick="toggleComments('{{ $post->id }}')">
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
        <button class="flex flex-col items-center gap-1" onclick="sharePost('{{ $post->id }}')">
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

    .pagination .page-item.loading .page-link {
        pointer-events: none;
        opacity: 0.7;
    }

    .comments-list {
        transition: opacity 0.3s ease;
        min-height: 50px;
    }

    button[type="submit"].submit-success {
        background-color: #10B981;
        transition: background-color 0.3s ease;
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

        commentDiv.innerHTML = `
    <div class="flex items-center mb-2">
        <img src="${profilePic}" alt="${comment.user.username}'s profile picture" class="w-8 h-8 rounded-full mr-2">
        <div>
            <div class="flex items-center">
                <a href="/@${comment.user.username}" class="text-sm font-medium text-gray-800 hover:underline">${comment.user.username}</a>
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
            if (pageItem.classList.contains('loading')) {
                return;
            }
            pageItem.classList.add('loading');

            setTimeout(() => {
                loadComments(postId, page);
                pageItem.classList.remove('loading');
            }, 100);
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

        const commentsSection = document.getElementById(`comments-section-${postId}`);
        const commentsContainer = commentsSection.querySelector('.comments-list');
        const currentHTML = commentsContainer.innerHTML;

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
                submitButton.classList.add('submit-success');
                setTimeout(() => {
                    submitButton.classList.remove('submit-success');
                }, 1500);

                if (data.errors) {
                    alert('Error: ' + Object.values(data.errors).join('\n'));
                    return;
                }

                const existingComments = commentsContainer.querySelectorAll('.comment');
                existingComments.forEach(comment => {
                    comment.style.opacity = '0.7';
                    comment.style.transition = 'opacity 0.3s ease';
                });

                setTimeout(() => {
                    loadComments(postId, 1);
                }, 300);

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
