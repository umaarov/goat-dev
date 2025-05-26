@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', __('messages.profile.title', ['username' => $user->username]))
@section('meta_description', __('messages.profile.meta_description', ['username' => $user->username]))

@section('content')
    <div class="max-w-3xl mx-auto">
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
                    <img src="{{ $profilePic }}"
                         alt="{{ __('messages.profile.alt_profile_picture', ['username' => $user->username]) }}"
                         class="h-24 w-24 rounded-full object-cover border border-gray-200 cursor-pointer zoomable-image flex-shrink-0"
                         {{-- Added flex-shrink-0 --}}
                         data-full-src="{{ $profilePic }}">
                    <div class="ml-6 flex-1">
                        {{-- Name / Username --}}
                        <div class="flex items-center">
                            <h2 class="text-2xl font-semibold text-gray-800">{{ $displayName }}</h2>
                            @if($isVerified)
                                <span class="ml-1.5" title="{{ __('messages.profile.verified_account') }}"> {{-- Adjusted margin slightly --}}
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

                        <p class="text-gray-500 text-xs mt-[4px]">{{ __('messages.profile.joined_label') }} {{ $user->created_at->format('M d, Y') }}</p>

                        {{-- Stats Section --}}
                        <div class="mt-2 flex space-x-5 text-sm">
                            <div>
                                <span class="font-semibold text-gray-800">{{ number_format($user->posts_count) }}</span>
                                <span
                                    class="text-gray-500">{{ trans_choice('messages.profile.posts_stat_label', $user->posts_count) }}</span>
                            </div>
                            <div>
                                <span
                                    class="font-semibold text-gray-800">{{ number_format($totalVotesOnUserPosts) }}</span>
                                <span
                                    class="text-gray-500">{{ trans_choice('messages.profile.votes_collected_stat_label', $totalVotesOnUserPosts) }}</span>
                            </div>
                        </div>

                        @if ($isOwnProfile)
                            <div class="mt-4">
                                <a href="{{ route('profile.edit') }}"
                                   class="px-4 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                    {{ __('messages.profile.edit_profile_button') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4 border-b border-gray-200">
        <div class="flex">
            <button id="load-my-posts" data-url="{{ route('profile.posts.data', $user->username) }}"
                    class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                {{ $isOwnProfile ? __('messages.profile.my_posts_tab') : __('messages.profile.users_posts_tab', ['username' => $user->username]) }}
                <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-800 transition-all duration-300"
                      id="my-posts-indicator"></span>
            </button>

            {{-- Voted Posts tab --}}
            @if ($isOwnProfile || $user->show_voted_posts_publicly)
                <button id="load-voted-posts" data-url="{{ route('profile.voted.data', $user->username) }}"
                        class="px-6 py-3 font-medium text-gray-700 hover:text-blue-800 focus:outline-none relative">
                    {{ __('messages.profile.voted_posts_tab') }}
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-transparent transition-all duration-300"
                          id="voted-posts-indicator"></span>
                </button>
            @endif
        </div>
    </div>

    <div id="posts-container" class="space-y-4">
        <p class="text-gray-500 text-center py-8">{{ __('messages.profile.loading_posts') }}</p>
    </div>
    </div>
@endsection

<style>
    /* Styles remain the same */
    .comments-section {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease-in-out, opacity 0.3s ease-in-out;
        opacity: 0;
    }

    .comments-section.active {
        max-height: 2000px; /* Adjust as needed */
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
        border-bottom: 1px solid #e5e7eb; /* Tailwind gray-200 */
        padding-bottom: 12px; /* 0.75rem */
        margin-bottom: 12px; /* 0.75rem */
    }

    .comment.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 4px; /* Tailwind space-x-1 approx */
        margin-top: 16px; /* Tailwind mt-4 */
        transition: opacity 0.3s ease;
    }

    .pagination .page-item {
        margin: 0;
    }

    .pagination .page-link {
        display: inline-block;
        padding: 5px 10px; /* Adjust as needed */
        border: 1px solid #e2e8f0; /* Tailwind border-gray-300 */
        border-radius: 4px; /* Tailwind rounded-sm approx */
        color: #4a5568; /* Tailwind text-gray-700 */
        text-decoration: none;
        transition: background-color 0.2s ease;
        cursor: pointer;
    }

    .pagination .page-link:hover {
        background-color: #edf2f7; /* Tailwind bg-gray-100 */
    }

    .pagination .page-item.active .page-link {
        background-color: #2563eb; /* Tailwind bg-blue-600 */
        color: white;
        border-color: #2563eb; /* Tailwind border-blue-600 */
    }

    .zoomable-image {
        cursor: pointer;
    }
</style>

@push('scripts')
    <script>
        window.isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};

        // Setup for JavaScript translations
        window.i18n = {
            profile: {
                js: {!! json_encode([
            'link_copied' => __('messages.profile.js.link_copied'),
            'time' => [
                'just_now' => __('messages.profile.js.time.just_now'),
                'minute'   => __('messages.profile.js.time.minute'),
                'minutes'  => __('messages.profile.js.time.minutes'),
                'hour'     => __('messages.profile.js.time.hour'),
                'hours'    => __('messages.profile.js.time.hours'),
                'day'      => __('messages.profile.js.time.day'),
                'days'     => __('messages.profile.js.time.days'),
                'ago'      => __('messages.profile.js.time.ago'),
            ],
            'no_comments'               => __('messages.profile.js.no_comments'),
            'no_comments_alt'           => __('messages.no_comments_yet'),
            'be_first_to_comment'       => __('messages.profile.js.be_first_to_comment'),
            'failed_load_comments'      => __('messages.profile.js.failed_load_comments'),
            'login_to_see_posts'        => __('messages.profile.js.login_to_see_posts'),
            'already_voted'             => __('messages.error_already_voted'),
            'vote_failed_http'          => __('messages.profile.js.vote_failed_http'),
            'vote_failed_connection'    => __('messages.profile.js.vote_failed_connection'),
            'confirm_delete_comment_title' => __('messages.profile.js.confirm_delete_comment_title'),
            'confirm_delete_comment_text'  => __('messages.profile.js.confirm_delete_comment_text'),
            'comment_empty'             => __('messages.profile.js.comment_empty'),
            'comment_button'            => __('messages.submit_comment_button'),
            'comment_button_submitting' => __('messages.profile.js.comment_button_submitting'),
            'error_prefix'              => __('messages.profile.js.error_prefix'),
            'delete_comment_title'      => __('messages.profile.js.delete_comment_title'),
            'loading'                   => __('messages.profile.js.loading'),
            'no_posts_found'            => __('messages.app.no_posts_found'),
            'load_more'                 => __('messages.profile.js.load_more'),
            'error_loading_posts'       => __('messages.profile.js.error_loading_posts'),
            'verified_account'          => __('messages.profile.verified_account'),
            'alt_profile_picture_js'    => __('messages.profile.alt_profile_picture_js'),
            'delete_comment_button_title' => __('messages.delete_button'),
        ]) !!}
            },
            pagination: {!! json_encode([
        'previous' => __('messages.pagination.previous'),
        'next'     => __('messages.pagination.next'),
    ]) !!}
        };

        // For the route, it's often easier to assign it directly if it's simple
        // or ensure the placeholder doesn't conflict with JSON processing.
        window.i18n.profile.js.user_profile_link_template = "{{ route('profile.show', ['username' => ':USERNAME_PLACEHOLDER']) }}".replace(':USERNAME_PLACEHOLDER', ':username');

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
                    top: postElement.offsetTop - 100, // Adjusted offset
                    behavior: 'smooth'
                });

                // Optional: Highlight the post
                postElement.classList.add('highlight-post'); // Define .highlight-post in your CSS
                setTimeout(() => {
                    postElement.classList.remove('highlight-post');
                }, 1500);
            }, 300); // Delay to ensure other content is loaded
        }


        function sharePost(postId) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            const questionElement = postElement.querySelector('.pt-4.px-4.font-semibold.text-center p');
            const question = questionElement ? questionElement.textContent : 'Check out this post'; // Fallback question
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

            if (window.showToast) {
                window.showToast(window.i18n.profile.js.link_copied);
            } else {
                alert(window.i18n.profile.js.link_copied);
            }
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
            const i18nTime = window.i18n.profile.js.time;

            if (diffInSeconds < 60) {
                return i18nTime.just_now;
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} ${minutes === 1 ? i18nTime.minute : i18nTime.minutes} ${i18nTime.ago}`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} ${hours === 1 ? i18nTime.hour : i18nTime.hours} ${i18nTime.ago}`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} ${days === 1 ? i18nTime.day : i18nTime.days} ${i18nTime.ago}`;
            } else {
                return date.toLocaleDateString(document.documentElement.lang || 'en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function openImageModal(event) {
            // event.stopPropagation();
            console.log('openImageModal triggered by:', event.target);
            const fullSrc = event.target.dataset.fullSrc || event.target.src;
            const altText = event.target.alt || (window.i18n.app && window.i18n.app.js && window.i18n.app.js.image_viewer_alt ? window.i18n.app.js.image_viewer_alt : 'View image'); // Added safe access for i18n

            const existingModal = document.getElementById('image-viewer-modal');
            if (existingModal) existingModal.remove();

            const modal = document.createElement('div');
            modal.id = 'image-viewer-modal';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.85)';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.zIndex = '10000';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'image-viewer-caption');

            const imgElement = document.createElement('img');
            imgElement.src = fullSrc;
            imgElement.alt = altText;
            imgElement.style.maxWidth = '90%';
            imgElement.style.maxHeight = '90%';
            imgElement.style.objectFit = 'contain';

            const closeButton = document.createElement('button');
            closeButton.innerHTML = '&times;';
            const closeTitle = (window.i18n.app && window.i18n.app.js && window.i18n.app.js.image_viewer_close_title ? window.i18n.app.js.image_viewer_close_title : 'Close'); // Safe access
            closeButton.title = closeTitle;
            closeButton.setAttribute('aria-label', closeTitle);
            closeButton.style.position = 'absolute';
            closeButton.style.top = '15px';
            closeButton.style.right = '25px';
            closeButton.style.fontSize = '2.5rem';
            closeButton.style.color = 'white';
            closeButton.style.background = 'transparent';
            closeButton.style.border = 'none';
            closeButton.style.cursor = 'pointer';

            const caption = document.createElement('div');
            caption.id = 'image-viewer-caption';
            caption.textContent = altText;
            caption.style.color = 'white';
            caption.style.textAlign = 'center';
            caption.style.position = 'absolute';
            caption.style.bottom = '20px';
            caption.style.width = '100%';
            caption.classList.add('sr-only');

            modal.appendChild(imgElement);
            modal.appendChild(closeButton);
            modal.appendChild(caption);
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';

            const closeModal = () => {
                modal.remove();
                document.body.style.overflow = '';
                document.removeEventListener('keydown', escKeyHandler);
            };

            const escKeyHandler = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                }
            };

            closeButton.onclick = closeModal;
            modal.onclick = (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            };
            document.addEventListener('keydown', escKeyHandler);
            imgElement.focus();
        }

        function initializeZoomableImages(parentElement = document) {
            console.log('Initializing zoomable images in:', parentElement);
            parentElement.querySelectorAll('.zoomable-image').forEach(image => {
                image.removeEventListener('click', openImageModal);
                image.addEventListener('click', openImageModal);
            });
        }

        // initializeZoomableImages();


        function initializePostInteractions() {
            const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
            commentsSections.forEach(section => {
                if (!section.classList.contains('comments-section')) {
                    section.classList.add('comments-section');
                }
                if (!section.classList.contains('active')) {
                    section.classList.add('hidden');
                }
                const formContainer = section.querySelector('form')?.closest('div');
                if (formContainer && !formContainer.classList.contains('comment-form-container')) {
                    formContainer.classList.add('comment-form-container');
                }
            });

            document.querySelectorAll('[data-action="share-post"]').forEach(button => {
                const postId = button.dataset.postId;
                if (!button.hasAttribute('data-share-initialized')) {
                    button.onclick = () => sharePost(postId);
                    button.setAttribute('data-share-initialized', 'true');
                }
            });

            document.querySelectorAll('[data-action="toggle-comments"]').forEach(button => {
                const postId = button.dataset.postId;
                if (!button.hasAttribute('data-toggle-initialized')) {
                    button.onclick = () => toggleComments(postId);
                    button.setAttribute('data-toggle-initialized', 'true');
                }
            });

            document.querySelectorAll('button.vote-button[data-post-id][data-option]').forEach(button => {
                const postId = button.dataset.postId;
                const option = button.dataset.option;
                if (!button.hasAttribute('data-vote-initialized')) {
                    button.onclick = () => voteForOption(postId, option);
                    button.setAttribute('data-vote-initialized', 'true');
                }
            });

            document.querySelectorAll('form[data-action="submit-comment"]').forEach(form => {
                const postId = form.dataset.postId;
                if (!form.hasAttribute('data-submit-initialized')) {
                    form.onsubmit = (event) => submitComment(postId, event);
                    form.setAttribute('data-submit-initialized', 'true');
                }
            });
            // initializeZoomableImages(document.getElementById('posts-container'));
        }


        let currentlyOpenCommentsId = null;

        function toggleComments(postId) {
            const clickedCommentsSection = document.getElementById(`comments-section-${postId}`);
            if (!clickedCommentsSection) return;

            if (currentlyOpenCommentsId && currentlyOpenCommentsId !== postId) {
                const previousCommentsSection = document.getElementById(`comments-section-${currentlyOpenCommentsId}`);
                if (previousCommentsSection) {
                    previousCommentsSection.classList.remove('active');
                    setTimeout(() => {
                        if (!previousCommentsSection.classList.contains('active')) {
                            previousCommentsSection.classList.add('hidden');
                        }
                    }, 500);
                }
            }

            if (clickedCommentsSection.classList.contains('hidden') || !clickedCommentsSection.classList.contains('active')) {
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
                currentlyOpenCommentsId = null;
                setTimeout(() => {
                    if (!clickedCommentsSection.classList.contains('active')) {
                        clickedCommentsSection.classList.add('hidden');
                    }
                }, 500)
            }
        }


        function loadComments(postId, page) {
            const commentsSection = document.getElementById(`comments-section-${postId}`);
            const commentsContainer = commentsSection.querySelector('.comments-list');

            if (!commentsContainer) {
                console.error('Comments container not found for post', postId);
                return;
            }

            let loadingIndicator = commentsContainer.querySelector('.comments-loading-indicator');
            if (!loadingIndicator) {
                loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'text-center py-4 comments-loading-indicator';
                loadingIndicator.innerHTML = `<div class="inline-block animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div>`;
            }


            if (page === 1) {
                commentsContainer.innerHTML = '';
                commentsContainer.appendChild(loadingIndicator);
            } else {
                const existingLoadMoreButton = commentsSection.querySelector('.load-more-comments-button');
                if (existingLoadMoreButton) existingLoadMoreButton.remove();
                commentsContainer.appendChild(loadingIndicator);
            }


            fetch(`/posts/${postId}/comments?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    loadingIndicator.remove();

                    if (data.comments.data.length === 0 && page === 1) {
                        commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.i18n.profile.js.no_comments_alt} ${window.i18n.profile.js.be_first_to_comment}</p>`;
                        return;
                    }
                    if (data.comments.data.length === 0 && page > 1) {
                        if (window.showToast) window.showToast('No more comments.', 'info');
                        return;
                    }


                    data.comments.data.forEach(comment => {
                        const commentDiv = createCommentElement(comment, postId);
                        commentsContainer.appendChild(commentDiv);
                    });

                    animateComments(commentsContainer);

                    const paginationContainer = commentsSection.querySelector(`#pagination-container-${postId}`);
                    if (paginationContainer) {
                        renderPagination(data.comments, postId, paginationContainer);
                    }

                    if (data.comments.current_page < data.comments.last_page) {
                        const loadMoreButton = document.createElement('button');
                        loadMoreButton.textContent = window.i18n.profile.js.load_more;
                        loadMoreButton.className = 'load-more-comments-button w-full mt-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm';
                        loadMoreButton.onclick = () => loadComments(postId, data.comments.current_page + 1);
                        commentsContainer.parentNode.appendChild(loadMoreButton);
                    }


                    commentsSection.dataset.loaded = "true";
                    commentsSection.dataset.currentPage = page;
                    // initializeZoomableImages(commentsContainer);
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    loadingIndicator.remove();
                    commentsContainer.innerHTML = `<p class="text-red-500 text-center">${window.i18n.profile.js.failed_load_comments}</p>`;
                });
        }

        async function voteForOption(postId, option) {
            if (!window.isLoggedIn) { // Use the global variable
                if (window.showToast) window.showToast(window.i18n.profile.js.login_to_vote, 'warning');
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

            // Prevent multiple clicks while processing
            if (clickedButton.disabled || clickedButton.classList.contains('voting-in-progress')) {
                return;
            }

            // Check if user has already voted based on UI state (if available and reliable)
            const knownUserVote = postElement.dataset.userVote;
            if (knownUserVote && (knownUserVote === 'option_one' || knownUserVote === 'option_two')) {
                // If the UI already reflects a vote, and the server confirms this,
                // re-sync UI if needed but don't send a new vote for the same option.
                // If they click the *other* option, the server should handle it (e.g., change vote or error).
                // For this example, if they click the *same* already voted option, we can just show the message.
                if (knownUserVote === option) {
                    if (window.showToast) window.showToast(window.i18n.profile.js.already_voted, 'info');
                    // Ensure UI is correctly reflecting the vote state (e.g., percentages)
                    // This might involve calling updateVoteUI with the current known data if it's not purely visual
                    const currentVoteData = {
                        user_vote: knownUserVote,
                        option_one_votes: parseInt(postElement.dataset.optionOneVotes, 10),
                        option_two_votes: parseInt(postElement.dataset.optionTwoVotes, 10),
                        total_votes: parseInt(postElement.dataset.optionOneVotes, 10) + parseInt(postElement.dataset.optionTwoVotes, 10),
                        message: window.i18n.profile.js.already_voted
                    };
                    updateVoteUI(postId, currentVoteData.user_vote, currentVoteData);
                    return;
                }
            }


            // Store original state for potential revert on error
            const originalOptionOneVotes = parseInt(postElement.dataset.optionOneVotes, 10) || 0;
            const originalOptionTwoVotes = parseInt(postElement.dataset.optionTwoVotes, 10) || 0;
            const originalUserVoteState = postElement.dataset.userVote || '';

            const originalClickedButtonClasses = Array.from(clickedButton.classList);
            const originalOtherButtonClasses = Array.from(otherButton.classList);

            const totalVotesDisplayElement = postElement.querySelector('.total-votes-display'); // Add a specific class for easier selection
            const originalTotalVotesText = totalVotesDisplayElement ? totalVotesDisplayElement.textContent : (originalOptionOneVotes + originalOptionTwoVotes).toString();


            // Optimistic UI update (basic visual cue)
            clickedButton.classList.add('voting-in-progress'); // e.g., for a spinner
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
                    updateVoteUI(postId, responseData.user_vote, responseData); // Main UI update with server data
                    if (window.showToast && responseData.message && !responseData.message.toLowerCase().includes('already voted')) {
                        window.showToast(responseData.message, 'success');
                    } else if (window.showToast && responseData.message && responseData.message.toLowerCase().includes('already voted')) {
                        window.showToast(responseData.message, 'info');
                    }
                } else { // HTTP errors (409, 422, 500, etc.)
                    const errorMessage = responseData.message || responseData.error || (responseData.errors ? Object.values(responseData.errors).join(', ') : `${window.i18n.profile.js.vote_failed_http} (HTTP ${response.status})`);
                    if (window.showToast) {
                        window.showToast(errorMessage, response.status === 409 ? 'info' : 'error');
                    }

                    // If it's a 'already voted' conflict (409) and server provides current vote state, update UI
                    if (response.status === 409 && responseData.user_vote && typeof responseData.option_one_votes !== 'undefined') {
                        updateVoteUI(postId, responseData.user_vote, responseData);
                    } else {
                        // Revert optimistic UI changes if it wasn't a 409 with new data
                        clickedButton.className = originalClickedButtonClasses.join(' '); // Restore classes
                        otherButton.className = originalOtherButtonClasses.join(' ');
                        postElement.dataset.userVote = originalUserVoteState;
                        postElement.dataset.optionOneVotes = originalOptionOneVotes.toString();
                        postElement.dataset.optionTwoVotes = originalOptionTwoVotes.toString();
                        if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;
                    }
                }
            } catch (error) {
                console.error('Error voting (catch):', error);
                if (window.showToast) {
                    window.showToast(window.i18n.profile.js.vote_failed_connection, 'error');
                }
                // Revert optimistic UI changes on network or other unhandled errors
                clickedButton.className = originalClickedButtonClasses.join(' ');
                otherButton.className = originalOtherButtonClasses.join(' ');
                postElement.dataset.userVote = originalUserVoteState;
                postElement.dataset.optionOneVotes = originalOptionOneVotes.toString();
                postElement.dataset.optionTwoVotes = originalOptionTwoVotes.toString();
                if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;

            } finally {
                clickedButton.classList.remove('voting-in-progress');
                // Re-enable buttons only if the action should allow another attempt
                // If a vote was successful or a "already voted" state is confirmed, they might remain visually styled as voted
                // but should technically be re-enabled for accessibility or if the vote can be changed.
                // For this example, always re-enable them after the attempt. The UI state will show the result.
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

            // Update dataset attributes which are crucial for state
            postElement.dataset.userVote = userVotedOption || ''; // Store which option user voted for, or empty if none/revoked
            postElement.dataset.optionOneVotes = voteData.option_one_votes;
            postElement.dataset.optionTwoVotes = voteData.option_two_votes;

            // Update total votes display
            const totalVotesDisplayElement = postElement.querySelector('.total-votes-display'); // Use a specific class
            if (totalVotesDisplayElement) {
                totalVotesDisplayElement.textContent = voteData.total_votes;
            }

            const optionOneButton = postElement.querySelector('button.vote-button[data-option="option_one"]');
            const optionTwoButton = postElement.querySelector('button.vote-button[data-option="option_two"]');

            if (optionOneButton && optionTwoButton) {
                const optionOneTitle = postElement.dataset.optionOneTitle || 'Option 1'; // Fallback title
                const optionTwoTitle = postElement.dataset.optionTwoTitle || 'Option 2'; // Fallback title

                const totalVotes = parseInt(voteData.total_votes, 10);
                const optionOneVotes = parseInt(voteData.option_one_votes, 10);
                const optionTwoVotes = parseInt(voteData.option_two_votes, 10);

                const percentOne = totalVotes > 0 ? Math.round((optionOneVotes / totalVotes) * 100) : 0;
                const percentTwo = totalVotes > 0 ? Math.round((optionTwoVotes / totalVotes) * 100) : 0;

                // Update button text to include percentages
                const optionOneTextElement = optionOneButton.querySelector('.button-text-truncate'); // Assuming text is in a span
                if (optionOneTextElement) {
                    optionOneTextElement.textContent = `${optionOneTitle} (${percentOne}%)`;
                } else { // Fallback if no inner span
                    optionOneButton.textContent = `${optionOneTitle} (${percentOne}%)`;
                }

                const optionTwoTextElement = optionTwoButton.querySelector('.button-text-truncate');
                if (optionTwoTextElement) {
                    optionTwoTextElement.textContent = `${optionTwoTitle} (${percentTwo}%)`;
                } else {
                    optionTwoButton.textContent = `${optionTwoTitle} (${percentTwo}%)`;
                }


                // Define classes for voted and non-voted states
                const highlightClasses = ['bg-blue-800', 'text-white', 'border-blue-800']; // Voted state
                const defaultClasses = ['bg-white', 'text-gray-700', 'border', 'border-gray-300', 'hover:bg-gray-50']; // Default, clickable
                const nonVotedPeerClasses = ['bg-gray-100', 'text-gray-600', 'border', 'border-gray-300']; // Other option when one is voted

                // Reset classes first
                [optionOneButton, optionTwoButton].forEach(button => {
                    button.classList.remove(...highlightClasses, ...defaultClasses, ...nonVotedPeerClasses);
                    // Remove any specific hover states if they are conditional
                    button.classList.remove('hover:bg-gray-50', 'hover:bg-blue-700');
                });


                if (userVotedOption) { // If there is a user vote
                    if (userVotedOption === 'option_one') {
                        optionOneButton.classList.add(...highlightClasses);
                        optionTwoButton.classList.add(...nonVotedPeerClasses); // Style for the non-selected option
                    } else if (userVotedOption === 'option_two') {
                        optionTwoButton.classList.add(...highlightClasses);
                        optionOneButton.classList.add(...nonVotedPeerClasses);
                    }
                    // After voting, tooltip might show "You voted for X" or just raw counts
                    optionOneButton.dataset.tooltipShowCount = "true"; // A flag to indicate results are shown
                    optionTwoButton.dataset.tooltipShowCount = "true";

                } else { // If no user vote (e.g., vote revoked or never voted)
                    optionOneButton.classList.add(...defaultClasses);
                    optionTwoButton.classList.add(...defaultClasses);
                    optionOneButton.dataset.tooltipShowCount = "false";
                    optionTwoButton.dataset.tooltipShowCount = "false";
                }
            }
        }

        function deleteComment(commentId, event) {
            event.preventDefault();

            if (!confirm(window.i18n.profile.js.confirm_delete_comment_text)) { // Use translated confirm message
                return;
            }

            const commentElement = document.getElementById('comment-' + commentId);
            if (!commentElement) {
                console.error('Comment element not found for deletion:', commentId);
                return;
            }

            const postId = commentElement.closest('[id^="comments-section-"]')?.id.split('-')[2];
            if (!postId) {
                console.error('Could not determine postId for comment deletion.');
                return;
            }

            // Optimistic UI update: fade out and prepare for removal
            commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
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
                .then(response => {
                    if (!response.ok) {
                        // If server returns an error, try to parse JSON for a message
                        return response.json().then(errData => {
                            throw new Error(errData.error || errData.message || `Server error: ${response.status}`);
                        }).catch(() => {
                            // If parsing error JSON fails, throw a generic error
                            throw new Error(`Failed to delete comment. Server responded with ${response.status}`);
                        });
                    }
                    return response.json(); // Assuming success returns JSON like { message: "Success" }
                })
                .then(data => {
                    // On successful deletion from server
                    // Wait for animation to complete, then remove
                    commentElement.addEventListener('transitionend', () => {
                        commentElement.remove();

                        // Update comment count on the post
                        const commentCountElement = document.querySelector(`#post-${postId} .comment-count-display`); // Use a specific class
                        if (commentCountElement) {
                            const currentCount = parseInt(commentCountElement.textContent) || 0;
                            commentCountElement.textContent = Math.max(0, currentCount - 1);
                        }

                        // Check if comments list is empty and update UI
                        const commentsSection = document.getElementById(`comments-section-${postId}`);
                        const commentsContainer = commentsSection?.querySelector('.comments-list');
                        if (commentsContainer && commentsContainer.children.length === 0) {
                            // If this was the last comment on the current page, and it's not page 1,
                            // you might want to reload the previous page or just show "no comments".
                            // For simplicity here, just show "no comments".
                            const currentPage = parseInt(commentsSection.dataset.currentPage) || 1;
                            if (currentPage > 1) {
                                // Option: loadComments(postId, currentPage - 1); // or current page, server will return empty if last one deleted from this page
                                commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.i18n.profile.js.no_comments_alt}</p>`; // Or a specific message
                            } else {
                                commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.i18n.profile.js.no_comments_alt} ${window.i18n.profile.js.be_first_to_comment}</p>`;
                            }
                            const paginationContainer = commentsSection?.querySelector(`#pagination-container-${postId}`);
                            if (paginationContainer) paginationContainer.innerHTML = ''; // Clear pagination
                        }
                    });

                    // Fallback if transitionend doesn't fire (e.g. element removed by other means, or no transition)
                    setTimeout(() => {
                        if (commentElement.parentNode) {
                            commentElement.remove();
                            // Repeat count update if not already done by transitionend
                        }
                    }, 350); // Slightly longer than transition

                    if (window.showToast && data.message) {
                        window.showToast(data.message, 'success');
                    }

                })
                .catch(error => {
                    console.error('Error deleting comment:', error);
                    // Revert optimistic UI update
                    commentElement.style.opacity = '1';
                    commentElement.style.transform = 'translateY(0)';
                    if (window.showToast) {
                        window.showToast(error.message || 'Failed to delete comment. Please try again.', 'error');
                    }
                });
        }

        function submitComment(postId, event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const contentInput = form.elements.content;
            const content = contentInput.value.trim();

            if (!content) {
                if (window.showToast) window.showToast(window.i18n.profile.js.comment_empty, 'warning');
                contentInput.focus();
                return;
            }

            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = `<div class="inline-block animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div> ${window.i18n.profile.js.comment_button_submitting}`;


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
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            // Join validation errors if they exist
                            const message = errData.errors ? Object.values(errData.errors).flat().join(' ') : (errData.message || 'Failed to submit comment.');
                            throw new Error(message);
                        }).catch(() => {
                            throw new Error(`Failed to submit comment. Server error: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.comment) { // Assuming server returns the created comment object
                        contentInput.value = ''; // Clear input field

                        const commentsSection = document.getElementById(`comments-section-${postId}`);
                        const commentsContainer = commentsSection?.querySelector('.comments-list');

                        if (commentsContainer) {
                            const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                            if (noCommentsMessage && (noCommentsMessage.textContent.includes(window.i18n.profile.js.no_comments_alt) || noCommentsMessage.textContent.includes("No comments yet"))) {
                                commentsContainer.innerHTML = ''; // Clear "No comments yet" message
                            }

                            const commentElement = createCommentElement(data.comment, postId); // data.comment should have all necessary fields
                            commentsContainer.insertBefore(commentElement, commentsContainer.firstChild); // Prepend new comment

                            setTimeout(() => commentElement.classList.add('visible'), 10); // Animate in

                            // Update comment count on the post
                            const commentCountElement = document.querySelector(`#post-${postId} .comment-count-display`);
                            if (commentCountElement) {
                                const currentCount = parseInt(commentCountElement.textContent) || 0;
                                commentCountElement.textContent = currentCount + 1;
                            }
                            // initializeZoomableImages(commentElement); // Make new comment image zoomable
                        }

                        if (window.showToast && data.message) {
                            window.showToast(data.message, 'success');
                        } else if (window.showToast) {
                            window.showToast('Comment posted!', 'success'); // Fallback success message
                        }

                    } else if (data.errors) { // Handle validation errors if structured this way
                        const errorMessages = Object.values(data.errors).flat().join(' ');
                        if (window.showToast) window.showToast(`${window.i18n.profile.js.error_prefix} ${errorMessages}`, 'error');
                    } else {
                        // Generic error if comment object is not in response as expected
                        if (window.showToast) window.showToast(data.message || 'Failed to add comment. Unexpected response.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error submitting comment:', error);
                    if (window.showToast) window.showToast(error.message || 'Failed to add comment. Please try again.', 'error');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText; // Restore original button text/content
                });
        }

        function createCommentElement(comment, postId) {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment mb-3 border-b border-gray-200 pb-3'; // Ensure 'comment' class for animation
            commentDiv.id = 'comment-' + comment.id;

            // Determine profile picture URL
            let profilePic = '/images/default-pfp.png'; // Default
            if (comment.user.profile_picture) {
                profilePic = comment.user.profile_picture.startsWith('http') ? comment.user.profile_picture : `/storage/${comment.user.profile_picture}`;
            }

            const isVerified = ['goat', 'umarov'].includes(comment.user.username); // Example verified users
            const verifiedIconHTML = isVerified ? `
                <span class="ml-1" title="${window.i18n.profile.js.verified_account}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </span>` : '';

            // User profile link
            const userProfileUrl = window.i18n.profile.js.user_profile_link_template.replace(':username', comment.user.username);
            // Sanitize comment content before injecting as HTML (Laravel's e() or similar on backend is preferred)
            // For JS, ensure the source 'comment.content' is already sanitized or use textContent for safety.
            // Here, assuming comment.content is safe or will be inserted into a text node context.
            // A simple precaution for display:
            const safeContent = document.createElement('p');
            safeContent.className = 'text-sm text-gray-700 break-words';
            safeContent.textContent = comment.content; // Safely sets text content

            const currentUserId = {{ Auth::id() ?? 'null' }};
            const canDelete = comment.user_id === currentUserId || (comment.post && comment.post.user_id === currentUserId);
            const deleteButtonHTML = canDelete ? `
                <div class="ml-auto pl-2">
                    <form onsubmit="deleteComment('${comment.id}', event)" class="inline">
                        <button type="submit" class="text-gray-400 hover:text-red-500 text-xs p-1" title="${window.i18n.profile.js.delete_comment_button_title}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </form>
                </div>` : '';

            commentDiv.innerHTML = `
                <div class="flex items-start mb-2">
                    <img src="${profilePic}" alt="${window.i18n.profile.js.alt_profile_picture_js.replace(':username', comment.user.username)}"
                         class="w-8 h-8 rounded-full mr-2 mt-1 cursor-pointer zoomable-image" data-full-src="${profilePic}">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <a href="${userProfileUrl}" class="text-sm font-medium text-gray-800 hover:underline">${comment.user.username}</a>
                            ${verifiedIconHTML}
                            <span class="mx-1 text-gray-400 text-xs">·</span>
                            <small class="text-xs text-gray-500" title="${new Date(comment.created_at).toLocaleString()}">${formatTimestamp(comment.created_at)}</small>
                        </div>
                        ${safeContent.outerHTML}
                    </div>
                    ${deleteButtonHTML}
                </div>
            `;
            return commentDiv;
        }

        function animateComments(container) {
            // Ensure only non-visible comments are animated
            const commentsToAnimate = container.querySelectorAll('.comment:not(.visible)');
            commentsToAnimate.forEach((comment, index) => {
                setTimeout(() => {
                    comment.classList.add('visible');
                }, 100 * (index + 1)); // Stagger animation
            });
        }

        function renderPagination(paginationData, postId, containerElement) {
            containerElement.innerHTML = ''; // Clear previous pagination

            if (!paginationData || paginationData.last_page <= 1) {
                return; // No pagination needed for one page or no data
            }

            const paginationNav = document.createElement('nav');
            paginationNav.setAttribute('aria-label', 'Comments pagination'); // Accessibility
            const paginationList = document.createElement('ul');
            paginationList.className = 'pagination'; // Your existing class for styling

            // Previous page link
            if (paginationData.current_page > 1) {
                paginationList.appendChild(createPageLink('&laquo;', paginationData.current_page - 1, postId, false, window.i18n.pagination.previous));
            }

            // Page number links (implement more sophisticated logic for many pages if needed, e.g., ellipses)
            for (let i = 1; i <= paginationData.last_page; i++) {
                paginationList.appendChild(createPageLink(i.toString(), i, postId, i === paginationData.current_page, `Go to page ${i}`));
            }

            // Next page link
            if (paginationData.current_page < paginationData.last_page) {
                paginationList.appendChild(createPageLink('&raquo;', paginationData.current_page + 1, postId, false, window.i18n.pagination.next));
            }

            paginationNav.appendChild(paginationList);
            containerElement.appendChild(paginationNav);
        }

        function createPageLink(text, page, postId, isActive = false, ariaLabel = '') {
            const pageItem = document.createElement('li'); // Use <li> for semantic list
            pageItem.className = `page-item ${isActive ? 'active' : ''}`;

            const link = document.createElement('a');
            link.className = 'page-link';
            link.href = '#'; // Prevent page jump, handle with JS
            link.innerHTML = text; // HTML entities like &laquo; will render as symbols
            if (ariaLabel) link.setAttribute('aria-label', ariaLabel);
            if (isActive) link.setAttribute('aria-current', 'page');


            link.onclick = (e) => {
                e.preventDefault();
                // Scroll to the top of the comments section or post when paginating
                const commentsSection = document.getElementById(`comments-section-${postId}`);
                if (commentsSection) {
                    // commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' }); // Option 1: scroll to section top
                    // Or scroll to the post itself, if preferred
                    const postElement = document.getElementById(`post-${postId}`);
                    if (postElement) {
                        window.scrollTo({top: postElement.offsetTop - 80, behavior: 'smooth'}); // Adjust offset
                    }

                }
                loadComments(postId, page);
            };

            pageItem.appendChild(link);
            return pageItem;
        }


        document.addEventListener('DOMContentLoaded', function () {
            const postsContainer = document.getElementById('posts-container');
            const myPostsButton = document.getElementById('load-my-posts');
            const myPostsIndicator = document.getElementById('my-posts-indicator');
            const votedPostsButton = document.getElementById('load-voted-posts'); // Can be null
            const votedPostsIndicator = votedPostsButton ? document.getElementById('voted-posts-indicator') : null;

            const buttons = [myPostsButton, votedPostsButton].filter(btn => btn != null);
            const indicators = [myPostsIndicator, votedPostsIndicator].filter(ind => ind != null);

            let currentPage = {}; // type: pageNumber
            let isLoading = {};   // type: boolean
            let hasMorePages = {};// type: boolean

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // initializeZoomableImages();

            function setActiveTab(activeButton) {
                buttons.forEach(btn => {
                    btn.classList.remove('text-blue-800', 'font-semibold');
                    btn.classList.add('text-gray-700'); // Default non-active state
                });

                indicators.forEach(ind => {
                    ind.classList.remove('bg-blue-800');
                    ind.classList.add('bg-transparent');
                });

                if (activeButton) {
                    activeButton.classList.remove('text-gray-700');
                    activeButton.classList.add('text-blue-800', 'font-semibold'); // Active state

                    const indicatorIdSuffix = activeButton.id.startsWith('load-my-posts') ? 'my-posts-indicator' : 'voted-posts-indicator';
                    const activeIndicator = document.getElementById(indicatorIdSuffix);

                    if (activeIndicator) {
                        activeIndicator.classList.remove('bg-transparent');
                        activeIndicator.classList.add('bg-blue-800');
                    }
                }
            }

            async function loadPosts(url, type, loadMore = false) {
                if (isLoading[type] && loadMore) return; // Prevent multiple 'load more' requests for the same type

                if (!loadMore) {
                    currentPage[type] = 1;
                    hasMorePages[type] = true; // Assume there are pages until checked
                    postsContainer.innerHTML = `<p class="text-center py-4">${window.i18n.profile.js.loading}</p>`;
                    // If switching tabs, ensure other type's loading state is reset if needed
                    Object.keys(isLoading).forEach(key => {
                        if (key !== type) isLoading[key] = false;
                    });
                } else {
                    if (!hasMorePages[type]) {
                        console.log('No more pages to load for', type);
                        const existingLoadMoreButton = postsContainer.querySelector(`.load-more-button[data-type="${type}"]`);
                        if (existingLoadMoreButton) existingLoadMoreButton.remove(); // Remove button if no more pages
                        return;
                    }
                    currentPage[type]++;
                }

                isLoading[type] = true;
                const fetchUrl = `${url}?page=${currentPage[type]}`;

                // Remove any existing "Load More" button before fetching new content (if loading more)
                if (loadMore) {
                    const existingLoadMoreButton = postsContainer.querySelector(`.load-more-button[data-type="${type}"]`);
                    if (existingLoadMoreButton) existingLoadMoreButton.remove();
                }


                try {
                    const response = await fetch(fetchUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest', // Important for Laravel to know it's AJAX
                            'Accept': 'application/json', // Expect JSON response with HTML and pagination data
                            'Content-Type': 'application/json',
                            // 'X-CSRF-TOKEN': csrfToken // Not typically needed for GET requests unless your middleware requires it
                        },
                        // credentials: 'same-origin' // Usually default
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const data = await response.json(); // Expecting { html: "...", hasMorePages: true/false }

                    if (!loadMore) {
                        postsContainer.innerHTML = data.html || `<p class="text-gray-500 text-center py-8">${window.i18n.profile.js.no_posts_found}</p>`;
                    } else {
                        // If there was a "Loading..." message for "load more", remove it.
                        // This usually isn't the case, the button itself is the indicator.
                        postsContainer.insertAdjacentHTML('beforeend', data.html || '');
                    }

                    initializePostInteractions(); // Re-initialize for new content (event delegation is better)

                    hasMorePages[type] = data.hasMorePages;

                    if (hasMorePages[type]) {
                        // Remove old button first to avoid duplicates if any logic error
                        const oldButton = postsContainer.querySelector(`.load-more-button[data-type="${type}"]`);
                        if (oldButton) oldButton.remove();

                        const loadMoreButton = document.createElement('button');
                        loadMoreButton.textContent = window.i18n.profile.js.load_more;
                        loadMoreButton.classList.add('load-more-button', 'w-full', 'mt-6', 'py-3', 'bg-gray-100', 'text-gray-700', 'rounded-md', 'hover:bg-gray-200', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
                        loadMoreButton.dataset.url = url;
                        loadMoreButton.dataset.type = type;
                        loadMoreButton.onclick = () => loadPosts(url, type, true);
                        postsContainer.appendChild(loadMoreButton);
                    } else {
                        const oldButton = postsContainer.querySelector(`.load-more-button[data-type="${type}"]`);
                        if (oldButton) oldButton.remove(); // Ensure button removed if no more pages
                        // If it was a "load more" action and no more pages, maybe a small "no more posts" message at the end
                        if (loadMore && postsContainer.querySelectorAll('.post-card-class').length > 0) { // Assuming posts have a common class like 'post-card-class'
                            // Optionally add a "No more posts to load" message
                        }
                    }

                    // If after loading (first page or more), there are no actual post elements, show "No posts found"
                    if (postsContainer.querySelectorAll('.post-card-class').length === 0 && !isLoading[type]) { // Check after isLoading is set to false
                        postsContainer.innerHTML = `<p class="text-gray-500 text-center py-8">${window.i18n.profile.js.no_posts_found}</p>`;
                    }


                } catch (error) {
                    console.error('Error loading posts:', error);
                    const usernameForMessage = "{{ $user->username }}"; // Get username from PHP
                    if (!window.isLoggedIn) { // Check global var
                        postsContainer.innerHTML = `
                            <div class="text-center py-4">
                                <p class="text-sm text-gray-500">
                                    ${window.i18n.profile.js.login_to_see_posts.replace(':username', usernameForMessage)}
                                </p>
                            </div>`;
                    } else {
                        postsContainer.innerHTML = `<p class="text-red-500 text-center py-8">${window.i18n.profile.js.error_loading_posts}</p>`;
                    }
                } finally {
                    isLoading[type] = false;
                }
            }

            // Initial load for the default tab (My Posts)
            if (myPostsButton) {
                myPostsButton.addEventListener('click', () => {
                    if (isLoading['my-posts'] && currentPage['my-posts'] > 1) return; // Prevent re-click spam if already loading more for this tab
                    setActiveTab(myPostsButton);
                    loadPosts(myPostsButton.dataset.url, 'my-posts');
                });
                // Set 'My Posts' as active and load its content by default
                setActiveTab(myPostsButton);
                loadPosts(myPostsButton.dataset.url, 'my-posts');
            }

            if (votedPostsButton) {
                votedPostsButton.addEventListener('click', () => {
                    if (isLoading['voted-posts'] && currentPage['voted-posts'] > 1) return;
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
        "description": "{{ __('messages.profile.schema_description', ['username' => $user->username]) }}",
        "mainEntityOfPage": {
            "@type": "ProfilePage",
            "@id": "{{ route('profile.show', ['username' => $user->username]) }}"
        }
    }
    </script>
@endsection
