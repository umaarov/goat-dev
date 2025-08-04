@php
    use Illuminate\Support\Facades\Auth;

@endphp

<script>

    // if (typeof window.postScriptInitialized === 'undefined') {
    //     window.postScriptInitialized = true;
    //     let typingTimers = {};
    var typingTimers = window.typingTimers || {};

    function updateTypingIndicatorUI(postId) {
            const indicator = document.getElementById(`typing-indicator-${postId}`);
            if (!indicator) return;

            const typists = indicator.dataset.typists ? JSON.parse(indicator.dataset.typists) : [];
            indicator.innerHTML = '';

            if (typists.length === 0) {
                return; // No one is typing
            }

            const span = document.createElement('span');

            const createStrong = (text) => {
                const strong = document.createElement('strong');
                strong.className = 'font-semibold not-italic';
                strong.textContent = text;
                return strong;
            };

            if (typists.length === 1) {
                // Case: User1 is typing...
                span.appendChild(createStrong(typists[0]));
                span.append(' is typing...');
            } else if (typists.length === 2) {
                // Case: User1 and User2 are typing...
                span.appendChild(createStrong(typists[0]));
                span.append(' and ');
                span.appendChild(createStrong(typists[1]));
                span.append(' are typing...');
            } else {
                // Case: User1 and X others are typing...
                const othersCount = typists.length - 1;
                span.appendChild(createStrong(typists[0]));
                span.append(' and ');
                span.appendChild(createStrong(othersCount + ' ' + (othersCount > 1 ? 'others' : 'other')));
                span.append(' are typing...');
            }

            indicator.appendChild(span);
        }

        function initializeTypingBroadcastingForPost(postId) {
            const currentUsername = @json(Auth::check() ? Auth::user()->username : null);
            if (!currentUsername) return;

            const form = document.getElementById(`comment-form-${postId}`);
            if (!form) return;

            const textarea = form.querySelector('textarea[name="content"]');
            if (!textarea || textarea.dataset.typingListenerAttached) return;
            textarea.dataset.typingListenerAttached = 'true';

            let typingTimeout;
            textarea.addEventListener('input', () => {
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    console.log(`[Whisper] Broadcasting 'typing' for post ${postId}`);
                    window.Echo.private(`post.${postId}`).whisper('typing', {
                        name: currentUsername
                    });
                }, 300);
            });
        }


    if (typeof window.imageObserver === 'undefined') {
        window.imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const image = entry.target;
                    loadImage(image);
                    observer.unobserve(image);
                }
            });
        }, {
            rootMargin: '0px 0px 100px 0px'
        });
    }


    function initializeImageLoading() {
        const imagesToLoad = document.querySelectorAll('.progressive-image[data-src]:not(.loaded)');
        imagesToLoad.forEach(img => {
            window.imageObserver.observe(img);
        });
    }

        function loadImage(imageElement) {
            const highResSrc = imageElement.dataset.src;
            if (!highResSrc) return;

            const tempImage = new Image();
            tempImage.src = highResSrc;

            tempImage.onload = () => {
                const container = imageElement.parentElement;

                imageElement.src = highResSrc;

                imageElement.classList.add('loaded');

                if (container && container.classList.contains('progressive-image-container')) {
                    container.classList.add('unblurred');
                }
            };

            tempImage.onerror = () => {
                console.error(`Failed to load image: ${highResSrc}`);
                const container = imageElement.parentElement;
                if (container && container.classList.contains('progressive-image-container')) {
                    container.classList.add('unblurred');
                }
            }
        }

        function initializeEchoListeners() {
            const currentUsername = @json(Auth::check() ? Auth::user()->username : null);
            if (!currentUsername) return;

            const postElements = document.querySelectorAll('article[id^="post-"]:not([data-echo-listening])');

            postElements.forEach(postElement => {
                postElement.dataset.echoListening = 'true';
                const postId = postElement.id.split('-')[1];

                const channel = window.Echo.private(`post.${postId}`);

                channel.listen('.NewCommentPosted', (e) => {
                    console.log('[Echo] Real-time comment received!', e);
                    const newCommentData = e.comment;
                    const commentsSection = document.getElementById(`comments-section-${postId}`);

                    if (String(newCommentData.post_id) === String(postId)) {
                        if (commentsSection && commentsSection.classList.contains('active')) {
                            addNewCommentToUI(newCommentData, postId);
                        }
                        const commentCountElement = document.querySelector(`#post-${postId} button[onclick^="toggleComments"] span`);
                        if (commentCountElement) {
                            commentCountElement.textContent = parseInt(commentCountElement.textContent) + 1;
                        }
                    }
                });

                channel.listenForWhisper('typing', (e) => {
                    console.log(`[Whisper] Received 'typing' event on post ${postId} from:`, e.name); // For debugging
                    const indicator = document.getElementById(`typing-indicator-${postId}`);

                    if (!indicator || e.name === currentUsername) {
                        return;
                    }

                    let typists = indicator.dataset.typists ? JSON.parse(indicator.dataset.typists) : [];

                    if (!typists.includes(e.name)) {
                        typists.push(e.name);
                    }
                    indicator.dataset.typists = JSON.stringify(typists);

                    const timerKey = `post-${postId}-${e.name}`;
                    if (typingTimers[timerKey]) {
                        clearTimeout(typingTimers[timerKey]);
                    }

                    typingTimers[timerKey] = setTimeout(() => {
                        let currentTypists = JSON.parse(indicator.dataset.typists || '[]');
                        const index = currentTypists.indexOf(e.name);
                        if (index > -1) {
                            currentTypists.splice(index, 1);
                        }
                        indicator.dataset.typists = JSON.stringify(currentTypists);
                        updateTypingIndicatorUI(postId);
                    }, 3000);

                    updateTypingIndicatorUI(postId);
                });
            });
        }


        function addNewCommentToUI(commentData, postId) {
            const commentElement = createCommentElement(commentData, postId, !!commentData.parent_id);
            const isReply = !!commentData.parent_id;

            if (isReply) {
                const rootCommentId = commentData.root_comment_id;
                const repliesContainer = document.querySelector(`#comment-${rootCommentId} .replies-container`);
                if (repliesContainer) {
                    repliesContainer.appendChild(commentElement);
                    updateParentUIAfterReply(rootCommentId);
                }
            } else {
                const commentsContainer = document.querySelector(`#comments-section-${postId} .comments-list`);
                if(commentsContainer){
                    const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                    if (noCommentsMessage) noCommentsMessage.remove();
                    commentsContainer.insertBefore(commentElement, commentsContainer.firstChild);
                }
            }

            commentElement.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            setTimeout(() => {
                commentElement.classList.add('visible');
                commentElement.style.backgroundColor = '';
            }, 10);
        }


        document.addEventListener('DOMContentLoaded', () => {
            // if (!('IntersectionObserver' in window)) {
            //     console.warn('IntersectionObserver not supported. Falling back to loading all images.');
            //     document.querySelectorAll('.progressive-image').forEach(img => loadImage(img));
            //     return;
            // }

            initializeImageLoading();

            initializeEchoListeners();

            document.addEventListener('posts-loaded', () => {
                initializeEchoListeners();
                initializeImageLoading();
            });

            // document.addEventListener('posts-loaded', initializeImageLoading);

            if ({{ Auth::check() ? 'true' : 'false' }}) {
                window.Echo.connector.pusher.connection.bind('connected', () => {
                    console.log('Real-time connection established! Enabling comment forms.');
                    const submitButtons = document.querySelectorAll('form[id^="comment-form-"] button[type="submit"]');
                    submitButtons.forEach(button => {
                        button.disabled = false;
                        button.classList.remove('bg-blue-400', 'cursor-not-allowed');
                        button.classList.add('bg-blue-800', 'hover:bg-blue-900');
                    });
                });
                window.Echo.connector.pusher.connection.bind('disconnected', () => {
                    console.log('Real-time connection lost! Disabling comment forms.');
                    const submitButtons = document.querySelectorAll('form[id^="comment-form-"] button[type="submit"]');
                    submitButtons.forEach(button => {
                        button.disabled = true;
                        button.classList.add('bg-blue-400', 'cursor-not-allowed');
                        button.classList.remove('bg-blue-800', 'hover:bg-blue-900');
                    });
                });
            }

            // const postElements = document.querySelectorAll('article[id^="post-"]');

            {{--postElements.forEach(postElement => {--}}
            {{--    const postId = postElement.id.split('-')[1];--}}
            {{--    if ({{ Auth::check() ? 'true' : 'false' }}) {--}}
            {{--        window.Echo.private(`post.${postId}`)--}}
            {{--            .listen('.NewCommentPosted', (e) => {--}}
            {{--                console.log('Real-time comment received!', e);--}}
            {{--                const newCommentData = e.comment;--}}
            {{--                const commentsSection = document.getElementById(`comments-section-${postId}`);--}}

            {{--                if (commentsSection && commentsSection.classList.contains('active')) {--}}
            {{--                    addNewCommentToUI(newCommentData, postId);--}}
            {{--                }--}}

            {{--                const commentCountElement = document.querySelector(`#post-${postId} button[onclick^="toggleComments"] span`);--}}
            {{--                if (commentCountElement) {--}}
            {{--                    commentCountElement.textContent = parseInt(commentCountElement.textContent) + 1;--}}
            {{--                }--}}
            {{--            });--}}
            {{--    }--}}
            {{--});--}}
        });

        function scrollToPost(postId) {
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;
            setTimeout(() => {
                window.scrollTo({top: postElement.offsetTop - 100, behavior: 'smooth'});
                postElement.classList.add('highlight-post');
                setTimeout(() => {
                    postElement.classList.remove('highlight-post');
                }, 1500);
            }, 300);
        }

        function sharePost(postId) {
            console.log(`[DEBUG] Attempting to share post #${postId}`);
            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            const questionElement = postElement.querySelector('.pt-4.px-4.font-semibold.text-center h2');
            if (!questionElement) {
                console.error('Could not find the question element for sharing.');
                return;
            }
            const question = questionElement.textContent;

            // const slug = question.toLowerCase()
            //     .replace(/[^\w\s-]/g, '')
            //     .replace(/\s+/g, '-')
            //     .substring(0, 60);
            // const shareUrl = `${window.location.origin}/p/${postId}/${slug}`;

            const shareUrl = postElement.dataset.shareUrl;

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
            showToast(window.translations.js_link_copied);
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

        function linkifyContent(text) {
            if (typeof text !== 'string') return '';
            const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
            const mentionRegex = /@([a-zA-Z0-9_]+)/g;

            let linkedText = text.replace(urlRegex, function(url, p1, p2, p3) {
                const fullUrl = p3 ? 'http://' + p3 : p1;
                return `<a href="${fullUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all">${url}</a>`;
            });

            linkedText = linkedText.replace(mentionRegex, function(match, username) {
                return `<a href="/@${username}" class="text-blue-600 hover:underline font-medium">@${username}</a>`;
            });

            return linkedText;
        }

        async function fetchAndShowComment(postId, commentId) {
            console.log(`[DEBUG] Starting fetchAndShowComment for post #${postId}, comment #${commentId}`);
            const commentsSection = document.getElementById(`comments-section-${postId}`);
            const commentsContainer = commentsSection.querySelector('.comments-list');
            const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
            if (!commentsSection || !commentsContainer || !paginationContainer) {
                console.error('[DEBUG] Could not find essential comment section elements. Aborting.');
                return;
            }

            if (window.currentlyOpenCommentsId && window.currentlyOpenCommentsId !== postId) {
                const previousSection = document.getElementById(`comments-section-${window.currentlyOpenCommentsId}`);
                if (previousSection) {
                    previousSection.classList.remove('active');
                    previousSection.classList.add('hidden');
                }
            }
            commentsSection.classList.remove('hidden');
            setTimeout(() => commentsSection.classList.add('active'), 10);
            window.currentlyOpenCommentsId = postId;

            commentsContainer.innerHTML = getCommentShimmerHTML();
            paginationContainer.innerHTML = '';

            try {
                const fetchUrl = `/posts/${postId}/comments/context/${commentId}`;
                console.log(`[DEBUG] Fetching comments from URL: ${fetchUrl}`);
                const response = await fetch(fetchUrl);

                if (!response.ok) {
                    throw new Error(`Failed to fetch comment context. Status: ${response.status}`);
                }
                const data = await response.json();
                console.log('[DEBUG] Successfully received data:', data);

                commentsContainer.innerHTML = '';
                if (data.comments.data.length === 0) {
                    commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.translations.js_no_comments_be_first}</p>`;
                    return;
                }

                const fragment = document.createDocumentFragment();
                data.comments.data.forEach(comment => {
                    fragment.appendChild(createCommentElement(comment, postId, false));
                });
                commentsContainer.appendChild(fragment);

                animateComments(commentsContainer);
                renderPagination(data.comments, postId, paginationContainer);
                commentsSection.dataset.loaded = "true";
                commentsSection.dataset.currentPage = data.comments.current_page;

                setTimeout(() => {
                    const fullCommentId = 'comment-' + commentId;
                    const targetElement = document.getElementById(fullCommentId);
                    console.log(`[DEBUG] Attempting to find final element with ID: ${fullCommentId}. Found:`, targetElement);

                    if (targetElement) {
                        const repliesContainer = targetElement.closest('.replies-container');
                        if (repliesContainer && repliesContainer.classList.contains('hidden')) {
                            console.log('[DEBUG] Target is a reply in a hidden container. Opening replies...');
                            const rootCommentElement = repliesContainer.closest('.comment');
                            const toggleButton = rootCommentElement.querySelector('.view-replies-button');
                            if (toggleButton) {
                                toggleButton.click();
                            }
                        }
                        console.log('[DEBUG] Scrolling to element...');
                        scrollToComment(fullCommentId);
                    } else {
                        console.error(`[DEBUG] FAILED! The comment element #${commentId} was not found in the HTML after loading.`);
                        if(window.showToast) {
                            window.showToast('Could not find the specific comment.', 'warning');
                        }
                    }
                }, 500);

            } catch (error) {
                console.error('[DEBUG] An error occurred during the fetch/render process:', error);
                commentsContainer.innerHTML = `<p class="text-red-500 text-center">${window.translations.js_failed_load_comments}</p>`;
            }
        }

        function toggleComments(postId) {
            const clickedCommentsSection = document.getElementById(`comments-section-${postId}`);
            if (!clickedCommentsSection) return;
            const isActive = clickedCommentsSection.classList.contains('active');

            if (window.currentlyOpenCommentsId && window.currentlyOpenCommentsId !== postId) {
                const previousCommentsSection = document.getElementById(`comments-section-${window.currentlyOpenCommentsId}`);
                if (previousCommentsSection) {
                    previousCommentsSection.classList.remove('active');
                    setTimeout(() => {
                        previousCommentsSection.classList.add('hidden');
                    }, 500);
                }
            }

            if (isActive) {
                clickedCommentsSection.classList.remove('active');
                window.currentlyOpenCommentsId = null;
                setTimeout(() => {
                    clickedCommentsSection.classList.add('hidden');
                }, 500);
            } else {
                clickedCommentsSection.classList.remove('hidden');
                setTimeout(() => {
                    clickedCommentsSection.classList.add('active');
                    window.currentlyOpenCommentsId = postId;
                    if (!clickedCommentsSection.dataset.loaded) {
                        loadComments(postId, 1);
                    }
                    initializeTypingBroadcastingForPost(postId);
                }, 10);
            }
        }

        function animateComments(container) {
            const comments = container.querySelectorAll(':scope > .comment:not(.visible)');

            comments.forEach((comment, index) => {
                const delay = Math.min(index * 80, 800);
                setTimeout(() => {
                    comment.classList.add('visible');
                }, delay);
            });
        }

        function getCommentShimmerHTML() {
            const template = document.getElementById('comment-shimmer-template');
            if (!template) {
                console.warn("Shimmer template not found. Using fallback.");
                return '<div class="text-center py-4 text-sm text-gray-500">Loading comments...</div>';
            }
            const shimmerContent = template.innerHTML;
            return shimmerContent.repeat(3);
        }

        function loadComments(postId, page) {
            const commentsSection = document.getElementById(`comments-section-${postId}`);
            const commentsContainer = commentsSection.querySelector('.comments-list');
            if (!commentsContainer) {
                console.error('Comments container not found');
                return;
            }
            const isLoggedIn = {{ Auth::check() ? 'true' : 'false' }};

            if (!isLoggedIn) {
                commentsContainer.innerHTML = `<div class="text-center py-4"><p class="text-sm text-gray-500">${window.translations.js_login_to_comment}</p></div>`;
                const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                if (paginationContainer) {
                    paginationContainer.innerHTML = '';
                }
                commentsSection.dataset.loaded = "true";
                return;
            }

            commentsContainer.innerHTML = getCommentShimmerHTML();

            fetch(`/posts/${postId}/comments?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    commentsContainer.innerHTML = '';

                    if (data.comments.data.length === 0 && page === 1) {
                        commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.translations.js_no_comments_be_first}</p>`;
                        return;
                    }

                    const fragment = document.createDocumentFragment();
                    data.comments.data.forEach(comment => {
                        const commentDiv = createCommentElement(comment, postId, false);
                        fragment.appendChild(commentDiv);

                    });
                    commentsContainer.appendChild(fragment);

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
                    commentsContainer.innerHTML = `<p class="text-red-500 text-center">${window.translations.js_failed_load_comments}</p>`;
                });
        }

        function createCommentElement(commentData, postId, isReply = false) {
            const commentDiv = document.createElement('div');
            // commentDiv.className = 'comment py-3 border-b border-gray-200';
            commentDiv.className = 'comment';

            // if (!isReply) {
            //     commentDiv.classList.add('border-b', 'border-gray-200');
            // }

            if (!commentData.id.toString().startsWith('temp-')) {
                commentDiv.id = 'comment-' + commentData.id;
            }

            const profilePic = commentData.user.profile_picture
                ? (commentData.user.profile_picture.startsWith('http') ? commentData.user.profile_picture : '/storage/' + commentData.user.profile_picture)
                : '/images/default-pfp.png';

            const altProfilePic = (window.translations.profile_alt_picture || 'Profile picture of :username').replace(':username', commentData.user.username);

            const isVerified = ['goat', 'umarov'].includes(commentData.user.username);
            const verifiedIconHTML = isVerified ? `<span class="ml-1 self-center" title="${window.translations.verified_account || 'Verified Account'}"><svg class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>` : '';


            const likesCount = commentData.likes_count || 0;
            const isLiked = commentData.is_liked_by_current_user || false;

            const filledHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />';
            const outlineHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656zM10 15.93l5.828-5.828a2 2 0 10-2.828-2.828L10 10.274l-2.828-2.829a2 2 0 00-2.828 2.828L10 15.93z" clip-rule="evenodd" />';

            const likeButtonHTML = `
            <div class="flex items-center text-xs">
                <button onclick="toggleCommentLike(${commentData.id}, this)"
                        class="like-comment-button flex items-center mb-0.5 rounded-full transition-all duration-150 ease-in-out group ${isLiked ? 'text-red-500 hover:bg-red-100' : 'text-gray-400 hover:text-red-500 hover:bg-red-50'}"
                        data-comment-id="${commentData.id}"
                        title="${isLiked ? (window.translations.unlike_comment_title || 'Unlike') : (window.translations.like_comment_title || 'Like')}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 comment-like-icon group-hover:scale-125 transition-transform duration-150" viewBox="0 0 20 20" fill="currentColor">
                        ${isLiked ? filledHeartSVG : outlineHeartSVG}
                    </svg>
                </button>
                <span class="ml-0.5 comment-likes-count font-medium tabular-nums ${parseInt(likesCount) === 0 ? 'text-gray-400' : 'text-gray-700'}" id="comment-likes-count-${commentData.id}">
                    ${likesCount}
                </span>
            </div>
        `;
            let replyToHTML = '';
            // if (commentData.parent_id && commentData.parent && commentData.parent.user) {
            //     const parentUsername = commentData.parent.user.username;
            //     if (!commentData.content.includes(`@${parentUsername}`)) {
            //         replyToHTML = `<a href="/@${parentUsername}" class="text-blue-600 hover:underline mr-1">@${parentUsername}</a>`;
            //     }
            // }
            // const isNestedReply = commentData.parent && commentData.parent.parent_id !== null;
            // if (isNestedReply) {
            //     if (commentData.parent.user) {
            //         const parentUsername = commentData.parent.user.username;
            //         if (!commentData.content.includes(`@${parentUsername}`)) {
            //             replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            //         }
            //     }
            // }

            // if (isReply) {
            //     console.log('%c DEBUG: Checking reply logic... ', 'background: #f2f2f2; color: #333;', {
            //         parent_id: commentData.parent_id,
            //         root_comment_id: commentData.root_comment_id,
            //         parent_id_type: typeof commentData.parent_id,
            //         root_comment_id_type: typeof commentData.root_comment_id,
            //     });
            //
            //     const isNestedReply = commentData.parent_id &&
            //         commentData.root_comment_id &&
            //         Number(commentData.parent_id) !== Number(commentData.root_comment_id);
            //
            //     console.log(`%c DEBUG: Is this a nested reply? -> ${isNestedReply}`, 'font-weight: bold;');
            //
            //     if (isNestedReply) {
            //         if (commentData.parent && commentData.parent.user) {
            //             const parentUsername = commentData.parent.user.username;
            //             if (!commentData.content.includes(`@${parentUsername}`)) {
            //                 console.log(`%c DEBUG: Decision: ADDING @mention for nested reply.`, 'color: green');
            //                 replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            //             }
            //         }
            //     } else {
            //         console.log(`%c DEBUG: Decision: NOT adding @mention.`, 'color: orange');
            //     }
            // }

            const isNestedReply = commentData.parent_id &&
                commentData.root_comment_id &&
                Number(commentData.parent_id) !== Number(commentData.root_comment_id);

            if (isNestedReply) {
                if (commentData.parent && commentData.parent.user) {
                    const parentUsername = commentData.parent.user.username;
                    if (!commentData.content.includes(`@${parentUsername}`)) {
                        replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
                    }
                }
            }

            // if (isReply && commentData.parent && commentData.parent.user && commentData.parent_id !== commentData.root_comment_id) {
            //     const parentUsername = commentData.parent.user.username;
            //     if (!commentData.content.includes(`@${parentUsername}`)) {
            //         replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            //     }
            // }

            const linkedCommentContent = linkifyContent(commentData.content);

            let repliesActionsHTML = '';

            let goToParentArrowHTML = '';
            if (isReply && Number(commentData.parent_id) !== Number(commentData.root_comment_id)) {
                goToParentArrowHTML = `<button onclick="scrollToComment('comment-${commentData.parent_id}')" class="p-1 rounded-full hover:bg-gray-200" title="${window.translations.go_to_parent_comment_title || 'Go to parent comment'}"><svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V5.612L5.28 9.68a.75.75 0 01-1.06-1.06l5.25-5.25a.75.75 0 011.06 0l5.25 5.25a.75.75 0 11-1.06 1.06L10.75 5.612V16.25A.75.75 0 0110 17z" clip-rule="evenodd" /></svg></button>`;
            }

            let repliesToggleHTML = '';
            if (!isReply && commentData.flat_replies && commentData.flat_replies.length > 0) {
                const replyCount = commentData.flat_replies.length;
                const viewText = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', replyCount);
                repliesToggleHTML = `<button class="view-replies-button font-semibold hover:underline" onclick="toggleRepliesContainer(this, 'comment-${commentData.id}')">${viewText}</button>`;
            }
            const replyButton = `<button onclick="prepareReply('${postId}', '${commentData.id}', '${commentData.user.username}')" class="font-semibold hover:underline" title="Reply to ${commentData.user.username}">${window.translations.reply_button_text || 'Reply'}</button>`;

            commentDiv.innerHTML = `
            <div class="flex items-start space-x-3">
                <img src="${profilePic}" alt="${altProfilePic}" loading="lazy" decoding="async" class="w-8 h-8 rounded-full flex-shrink-0 cursor-pointer zoomable-image" data-full-src="${profilePic}">
                <div class="flex-1">
                    <div class="text-sm">
                        <div class="flex items-center">
                            <a href="/@${commentData.user.username}" class="font-semibold text-gray-900 hover:underline">${commentData.user.username}</a>
                                            ${verifiedIconHTML}
                        </div>
                        <span class="text-gray-800">${replyToHTML} ${linkedCommentContent}</span>
                    </div>
                    <div class="comment-actions mt-1.5 flex items-center space-x-3 text-xs text-gray-500">
                        <small class="text-xs text-gray-500" title="${commentData.created_at}">${formatTimestamp(commentData.created_at)}</small>
                        ${ {{ Auth::check() ? 'true' : 'false' }} ? `<div class="flex items-center">${likeButtonHTML}</div>` : ''}
                        ${replyButton}
                        ${repliesActionsHTML}
                        ${goToParentArrowHTML}
                    </div>
                </div>
${canDeleteComment(commentData) ? `
                <div class="ml-2 pl-1 flex-shrink-0">
                    <form onsubmit="deleteComment('${commentData.id}', event)" class="inline">
                        <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors duration-150 ease-in-out text-xs p-1 mt-0.5" title="${window.translations.delete_comment_title || 'Delete comment'}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </form>
                </div>` : ''}
            </div>
            <div class="replies-container hidden"></div>
            `;
            const actionsContainer = commentDiv.querySelector('.comment-actions');
            const repliesContainer = commentDiv.querySelector('.replies-container');

            if (!isReply) {
                const loadedRepliesCount = commentData.flat_replies ? commentData.flat_replies.length : 0;
                const totalRepliesCount = commentData.replies_count || 0;
                const hasMoreReplies = totalRepliesCount > loadedRepliesCount;

                if (totalRepliesCount > 0) {
                    const toggleButton = document.createElement('button');
                    toggleButton.className = 'view-replies-button font-semibold hover:underline';
                    toggleButton.textContent = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', totalRepliesCount);
                    toggleButton.onclick = () => toggleRepliesContainer(toggleButton, 'comment-' + commentData.id);
                    actionsContainer.appendChild(toggleButton);
                }

                if (loadedRepliesCount > 0) {
                    commentData.flat_replies.forEach(replyData => {
                        const replyElement = createCommentElement(replyData, postId, true);
                        repliesContainer.appendChild(replyElement);
                    });
                }

                if (hasMoreReplies) {
                    const remainingCount = totalRepliesCount - loadedRepliesCount;
                    const loadMoreWrapper = document.createElement('div');
                    loadMoreWrapper.className = 'load-more-replies-wrapper mt-2'; // Added margin
                    loadMoreWrapper.innerHTML = `<button class="text-xs font-semibold text-blue-600 hover:underline" onclick="loadMoreReplies(this, ${commentData.id})">${(window.translations.view_more_replies_text || 'View :count more replies').replace(':count', remainingCount)}</button>`;
                    repliesContainer.appendChild(loadMoreWrapper);
                }
            }

            return commentDiv;
        }

        function scrollToComment(commentId) {
            const element = document.getElementById(commentId);

            if (!element) {
                if (window.showToast) {
                    const message = window.translations.js_parent_comment_deleted || 'The comment you are replying to has been deleted.';
                    window.showToast(message, 'warning');
                } else {
                    console.warn(`Could not find comment ${commentId} to scroll to.`);
                }
                return;
            }

            const repliesContainer = element.closest('.replies-container');
            if (repliesContainer && repliesContainer.classList.contains('hidden')) {
                const rootComment = repliesContainer.closest('.comment');
                const toggleButton = rootComment.querySelector('.view-replies-button');
                if (toggleButton) {
                    toggleButton.click();
                }
            }

            setTimeout(() => {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                element.classList.add('highlighted-comment');
                setTimeout(() => {
                    element.classList.remove('highlighted-comment');
                }, 1500);
            }, 100);
        }

        function toggleRepliesContainer(button, commentId) {
            const commentElement = document.getElementById(commentId);
            if (!commentElement) return;

            const repliesContainer = commentElement.querySelector('.replies-container');
            if (!repliesContainer) return;

            const loadMoreWrapper = commentElement.querySelector('.load-more-replies-wrapper');
            const isHidden = repliesContainer.classList.contains('hidden');

            if (isHidden) {
                repliesContainer.classList.remove('hidden');

                setTimeout(() => {
                    animateComments(repliesContainer);
                }, 50);

                if (loadMoreWrapper) {
                    loadMoreWrapper.classList.remove('hidden');
                }

                button.classList.add('active');
                button.textContent = window.translations.hide_replies_text || 'Hide replies';

            } else {
                repliesContainer.classList.add('hidden');
                button.classList.remove('active');

                const existingRepliesCount = repliesContainer.querySelectorAll('.comment').length;
                const loadMoreButton = repliesContainer.querySelector('.load-more-replies-wrapper button');
                let totalCount = existingRepliesCount;

                if (loadMoreButton) {
                    const matches = loadMoreButton.textContent.match(/\d+/);
                    if (matches) {
                        const remainingCount = parseInt(matches[0], 10);
                        totalCount = existingRepliesCount + remainingCount;
                    }
                }
                button.textContent = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', totalCount);
            }
        }

        async function loadMoreReplies(button, rootCommentId) {
            const commentElement = document.getElementById(`comment-${rootCommentId}`);
            if (!commentElement) return;

            const repliesContainer = commentElement.querySelector('.replies-container');
            const existingReplyIds = Array.from(repliesContainer.querySelectorAll('.comment[id^="comment-"]')).map(el => el.id.replace('comment-', ''));

            button.disabled = true;
            button.textContent = 'Loading...';
            const loadMoreWrapper = button.parentElement;

            const excludeParams = existingReplyIds.map(id => `exclude_ids[]=${id}`).join('&');
            const url = `/comments/${rootCommentId}/replies?${excludeParams}`;

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error('Network response was not ok.');
                const paginatedReplies = await response.json();

                const fragment = document.createDocumentFragment();
                paginatedReplies.data.forEach(reply => {
                    const replyDiv = createCommentElement(reply, reply.post_id, true);
                    fragment.appendChild(replyDiv);
                });
                repliesContainer.insertBefore(fragment, loadMoreWrapper);

                animateComments(repliesContainer);

                const totalLoaded = existingReplyIds.length + paginatedReplies.data.length;
                const totalAvailable = paginatedReplies.total;
                if (totalLoaded >= totalAvailable) {
                    loadMoreWrapper.remove();
                } else {
                    const remaining = totalAvailable - totalLoaded;
                    button.textContent = (window.translations.view_more_replies_text || 'View :count more replies').replace(':count', remaining);
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Error loading more replies:', error);
                button.textContent = 'Error. Click to retry.';
                button.disabled = false;
            }
        }


        function prepareReply(postId, commentId, username) {
            const form = document.getElementById(`comment-form-${postId}`);
            if (!form) return;

            const targetCommentElement = document.getElementById(`comment-${commentId}`);
            if (!targetCommentElement) {
                console.error(`Could not find comment element with ID: comment-${commentId}`);
                return;
            }

            const isTargetANestedReply = targetCommentElement.parentElement.classList.contains('replies-container');

            form.elements.parent_id.value = commentId;
            const textarea = form.elements.content;

            if (isTargetANestedReply) {
                textarea.value = `@${username} `;
            } else {
                textarea.value = '';
            }

            textarea.focus();
            const end = textarea.value.length;
            textarea.setSelectionRange(end, end);

            const indicator = document.getElementById(`reply-indicator-${postId}`);
            indicator.querySelector('span').textContent = `Replying to @${username}`;
            indicator.classList.remove('hidden');
            indicator.classList.add('flex');

            if (targetCommentElement) {
                const repliesContainer = targetCommentElement.querySelector('.replies-container');
                if (repliesContainer && repliesContainer.classList.contains('hidden')) {
                    const toggleButton = targetCommentElement.querySelector('.view-replies-button');
                    if (toggleButton) {
                        toggleButton.click();
                    }
                }
            }
        }

        function cancelReply(postId) {
            const form = document.getElementById(`comment-form-${postId}`);
            if (!form) return;

            form.elements.parent_id.value = '';
            form.elements.content.value = '';

            const indicator = document.getElementById(`reply-indicator-${postId}`);
            indicator.classList.add('hidden');
            indicator.classList.remove('flex');
        }

        async function toggleCommentLike(commentId, buttonElement) {
            if (!{{ Auth::check() ? 'true' : 'false' }}) {
                if (window.showToast) window.showToast(window.translations.js_login_to_like_comment || 'Please login to like comments.', 'warning');
                return;
            }

            if (buttonElement.disabled) {
                return;
            }

            buttonElement.disabled = true;
            buttonElement.classList.add('processing-like');

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const url = `/comments/${commentId}/toggle-like`;

            const heartIcon = buttonElement.querySelector('.comment-like-icon');
            const likesCountSpan = document.getElementById(`comment-likes-count-${commentId}`);

            const originallyLiked = buttonElement.classList.contains('text-red-500');
            const originalLikesCount = parseInt(likesCountSpan.textContent.trim());

            const filledHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />';
            const outlineHeartSVG = '<path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656zM10 15.93l5.828-5.828a2 2 0 10-2.828-2.828L10 10.274l-2.828-2.829a2 2 0 00-2.828 2.828L10 15.93z" clip-rule="evenodd" />';

            let newLikesCount;
            if (originallyLiked) {
                buttonElement.classList.remove('text-red-500', 'hover:bg-red-100');
                buttonElement.classList.add('text-gray-400', 'hover:text-red-500', 'hover:bg-red-50');
                heartIcon.innerHTML = outlineHeartSVG;
                buttonElement.title = window.translations.like_comment_title || 'Like';
                newLikesCount = Math.max(0, originalLikesCount - 1);
            } else {
                buttonElement.classList.remove('text-gray-400', 'hover:text-red-500', 'hover:bg-red-50');
                buttonElement.classList.add('text-red-500', 'hover:bg-red-100');
                heartIcon.innerHTML = filledHeartSVG;
                buttonElement.title = window.translations.unlike_comment_title || 'Unlike';
                newLikesCount = originalLikesCount + 1;
            }
            likesCountSpan.textContent = newLikesCount;
            likesCountSpan.className = `ml-1.5 comment-likes-count font-medium tabular-nums ${newLikesCount === 0 ? 'text-gray-400' : 'text-gray-700'}`;


            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const commentElement = document.getElementById(`comment-${commentId}`);

                    if (response.status === 404) {
                        if (window.showToast) window.showToast('This comment has been deleted.', 'error');
                        if (commentElement) {
                            commentElement.style.transition = 'opacity 0.3s ease';
                            commentElement.style.opacity = '0';
                            setTimeout(() => commentElement.remove(), 300);
                        }
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to toggle like.');
                    }
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || data.error_dev || 'Failed to toggle like.');
                }

                buttonElement.classList.toggle('text-red-500', data.is_liked);
                buttonElement.classList.toggle('hover:bg-red-100', data.is_liked);
                buttonElement.classList.toggle('text-gray-400', !data.is_liked);
                buttonElement.classList.toggle('hover:text-red-500', !data.is_liked);
                buttonElement.classList.toggle('hover:bg-red-50', !data.is_liked);


                if (data.is_liked) {
                    heartIcon.innerHTML = filledHeartSVG;
                    buttonElement.title = window.translations.unlike_comment_title || 'Unlike';
                } else {
                    heartIcon.innerHTML = outlineHeartSVG;
                    buttonElement.title = window.translations.like_comment_title || 'Like';
                }
                likesCountSpan.textContent = data.likes_count;
                likesCountSpan.className = `ml-1.5 comment-likes-count font-medium tabular-nums ${parseInt(data.likes_count) === 0 ? 'text-gray-400' : 'text-gray-700'}`;


                if (window.showToast && data.message_user) {
                    // window.showToast(data.message_user, 'success');
                }

            } catch (error) {
                console.error('Error toggling comment like:', error);
                if (window.showToast) window.showToast(error.message || (window.translations.js_error_liking_comment || 'Could not update like.'), 'error');

                buttonElement.classList.toggle('text-red-500', originallyLiked);
                buttonElement.classList.toggle('hover:bg-red-100', originallyLiked);
                buttonElement.classList.toggle('text-gray-400', !originallyLiked);
                buttonElement.classList.toggle('hover:text-red-500', !originallyLiked);
                buttonElement.classList.toggle('hover:bg-red-50', !originallyLiked);


                if (originallyLiked) {
                    heartIcon.innerHTML = filledHeartSVG;
                    buttonElement.title = window.translations.unlike_comment_title || 'Unlike';
                } else {
                    heartIcon.innerHTML = outlineHeartSVG;
                    buttonElement.title = window.translations.like_comment_title || 'Like';
                }
                likesCountSpan.textContent = originalLikesCount;
                likesCountSpan.className = `ml-1.5 comment-likes-count font-medium tabular-nums ${originalLikesCount === 0 ? 'text-gray-400' : 'text-gray-700'}`;
            } finally {
                buttonElement.disabled = false;
                buttonElement.classList.remove('processing-like');
            }
        }

        function canDeleteComment(comment) {
            const currentUserId = {{ Auth::id() ?? 'null' }};
            if (currentUserId === null) return false;
            const commentOwnerId = parseInt(comment.user_id, 10);
            const postOwnerId = comment.post ? parseInt(comment.post.user_id, 10) : null;
            if (commentOwnerId === currentUserId) {
                return true;
            }
            return postOwnerId !== null && postOwnerId === currentUserId;
        }

        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            const lang = document.documentElement.lang;

            if (diffInSeconds < 60) {
                return window.translations.time_just_now;
            } else if (diffInSeconds < 3600) {
                const count = Math.floor(diffInSeconds / 60);
                if (lang === 'ru') {
                    if (count === 1) return `${count} ${window.translations.time_minute} ${window.translations.time_ago}`;
                    if (count >= 2 && count <= 4) return `${count} ${window.translations.time_minutes} ${window.translations.time_ago}`;
                    return `${count} ${window.translations.time_minutes_alt} ${window.translations.time_ago}`;
                }
                return `${count} ${count === 1 ? window.translations.time_minute : window.translations.time_minutes} ${window.translations.time_ago}`;
            } else if (diffInSeconds < 86400) {
                const count = Math.floor(diffInSeconds / 3600);
                if (lang === 'ru') {
                    if (count === 1) return `${count} ${window.translations.time_hour} ${window.translations.time_ago}`;
                    if (count >= 2 && count <= 4) return `${count} ${window.translations.time_hours} ${window.translations.time_ago}`;
                    return `${count} ${window.translations.time_hours_alt} ${window.translations.time_ago}`;
                }
                return `${count} ${count === 1 ? window.translations.time_hour : window.translations.time_hours} ${window.translations.time_ago}`;
            } else if (diffInSeconds < 604800) {
                const count = Math.floor(diffInSeconds / 86400);
                if (lang === 'ru') {
                    if (count === 1) return `${count} ${window.translations.time_day} ${window.translations.time_ago}`;
                    if (count >= 2 && count <= 4) return `${count} ${window.translations.time_days} ${window.translations.time_ago}`;
                    return `${count} ${window.translations.time_days_alt} ${window.translations.time_ago}`;
                }
                return `${count} ${count === 1 ? window.translations.time_day : window.translations.time_days} ${window.translations.time_ago}`;
            } else {
                return date.toLocaleDateString(lang === 'ru' ? 'ru-RU' : 'en-US');
            }
        }


        function renderPagination(comments, postId, container) {
            container.innerHTML = '';
            if (comments.last_page <= 1) return;

            const pagination = document.createElement('div');
            pagination.className = 'pagination';

            // Previous page link
            if (comments.current_page > 1) {
                pagination.appendChild(createPageLink('&laquo;', comments.current_page - 1, postId));
            } else {
                const disabledPrev = document.createElement('div');
                disabledPrev.className = 'page-item disabled';
                disabledPrev.innerHTML = '<span class="page-link">&laquo;</span>';
                pagination.appendChild(disabledPrev);
            }

            // Page number links
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
                pagination.appendChild(createPageLink(i.toString(), i, postId, i === comments.current_page));
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

            // Next page link
            if (comments.current_page < comments.last_page) {
                pagination.appendChild(createPageLink('&raquo;', comments.current_page + 1, postId));
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
                if (pageItem.classList.contains('active')) return;
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

        function updateParentUIAfterReply(rootCommentId) {
            const rootCommentEl = document.getElementById(`comment-${rootCommentId}`);
            if (!rootCommentEl) return;

            const repliesContainer = rootCommentEl.querySelector('.replies-container');
            let toggleButton = rootCommentEl.querySelector('.view-replies-button');
            const actionsContainer = rootCommentEl.querySelector('.mt-1\\.5.flex');

            if (!toggleButton && actionsContainer) {
                toggleButton = document.createElement('button');
                toggleButton.className = 'view-replies-button font-semibold hover:underline text-xs text-gray-500';
                toggleButton.onclick = () => toggleRepliesContainer(toggleButton, `comment-${rootCommentId}`);
                actionsContainer.appendChild(toggleButton);
            }

            if (toggleButton) {
                const replyCount = repliesContainer.children.length;
                const viewText = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', replyCount);

                toggleButton.textContent = window.translations.hide_replies_text || 'Hide replies';

                if(repliesContainer.classList.contains('hidden')) {
                    repliesContainer.classList.remove('hidden');
                }
            }
        }

        function submitComment(postId, event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const content = form.elements.content.value;
            const parentId = form.elements.parent_id.value;

            if (!content.trim()) {
                if(window.showToast) showToast(window.translations.js_comment_empty);
                return;
            }

            submitButton.disabled = true;
            submitButton.innerHTML = `<div class="inline-block animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>`;

            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'X-Socket-ID': window.Echo.socketId()
            };


            fetch(`/posts/${postId}/comments`, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({content: content, parent_id: parentId || null})
            })
                .then(response => {
                    if (!response.ok) return response.json().then(err => Promise.reject(err));
                    return response.json();
                })
                .then(data => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = window.translations.js_submit_comment_button || 'Submit';
                    cancelReply(postId);
                    form.elements.content.value = '';

                    const newComment = data.comment;
                    const isReply = !!newComment.parent_id;

                    const commentElement = createCommentElement(newComment, postId, isReply);

                    if (isReply) {
                        const rootCommentId = newComment.root_comment_id;
                        const repliesContainer = document.querySelector(`#comment-${rootCommentId} .replies-container`);
                        if (repliesContainer) {
                            repliesContainer.appendChild(commentElement);
                            updateParentUIAfterReply(rootCommentId);
                        } else {
                            loadComments(postId, 1);
                        }
                    } else {
                        const commentsContainer = document.querySelector(`#comments-section-${postId} .comments-list`);
                        const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                        if (noCommentsMessage) noCommentsMessage.remove();
                        commentsContainer.insertBefore(commentElement, commentsContainer.firstChild);
                    }

                    setTimeout(() => { commentElement.classList.add('visible'); }, 10);

                    const commentCountElement = document.querySelector(`#post-${postId} button[onclick^="toggleComments"] span`);
                    if (commentCountElement) {
                        commentCountElement.textContent = parseInt(commentCountElement.textContent) + 1;
                    }
                    if (window.showToast) showToast(data.message || 'Comment posted!', 'success');
                })
                .catch(errorData => {
                    console.error('Error:', errorData);
                    submitButton.disabled = false;
                    submitButton.innerHTML = window.translations.js_submit_comment_button || 'Submit';
                    const errorMessage = errorData?.errors ? Object.values(errorData.errors).join(' ') : (window.translations.js_failed_add_comment || 'Failed to add comment.');
                    if (window.showToast) showToast(errorMessage, 'error');
                });
        }

        function deleteComment(commentId, event) {
            event.preventDefault();
            if (!confirm(window.translations.js_confirm_delete_comment_text)) return;

            const commentElement = document.getElementById('comment-' + commentId);
            if (!commentElement) return;
            const postIdElement = commentElement.closest('[id^="comments-section-"]');
            if (!postIdElement) return;
            const postId = postIdElement.id.split('-')[2];

            commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            commentElement.style.opacity = '0';
            commentElement.style.transform = 'translateY(10px)';

            const url = `/comments/${commentId}`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(url, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                credentials: 'include'
            })
                .then(response => {
                    if (!response.ok) return response.json().then(err => {
                        throw err;
                    });
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showToast(data.error);
                        commentElement.style.opacity = '1';
                        commentElement.style.transform = 'translateY(0)';
                        return;
                    }
                    const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                    if (commentCountElement) {
                        const currentCount = parseInt(commentCountElement.textContent);
                        if (!isNaN(currentCount) && currentCount > 0) {
                            commentCountElement.textContent = currentCount - 1;
                        }
                    }
                    setTimeout(() => {
                        commentElement.remove();
                        const commentsSection = document.getElementById(`comments-section-${postId}`);
                        const commentsContainer = commentsSection.querySelector('.comments-list');
                        if (commentsContainer.querySelectorAll('.comment').length === 0) {
                            const currentPage = parseInt(commentsSection.dataset.currentPage) || 1;
                            if (currentPage > 1) {
                                loadComments(postId, currentPage - 1);
                            } else {
                                commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.translations.js_no_comments_be_first}</p>`;
                                const paginationContainer = document.querySelector(`#pagination-container-${postId}`);
                                if (paginationContainer) paginationContainer.innerHTML = '';
                                commentsSection.dataset.loaded = "true";
                            }
                        }
                    }, 300);
                })
                .catch(error => {
                    console.error('Error deleting comment:', error);
                    commentElement.style.opacity = '1';
                    commentElement.style.transform = 'translateY(0)';
                    const errorMessage = error?.message || error?.error || window.translations.js_failed_delete_comment;
                    showToast(String(errorMessage));
                });
        }

        async function voteForOption(postId, option) {
            if (!{{ Auth::check() ? 'true' : 'false' }}) {
                if (window.showToast) window.showToast(window.translations.js_login_to_vote, 'warning');
                return;
            }

            const postElement = document.getElementById(`post-${postId}`);
            if (!postElement) return;

            const clickedButton = postElement.querySelector(`button.vote-button[data-option="${option}"]`);
            const otherButtonOption = option === 'option_one' ? 'option_two' : 'option_one';
            const otherButton = postElement.querySelector(`button.vote-button[data-option="${otherButtonOption}"]`);
            if (clickedButton.disabled || otherButton.disabled) {
                console.log('Vote already in progress. Ignoring click.');
                return;
            }
            if (!clickedButton || !otherButton || clickedButton.disabled) return;

            const knownUserVote = postElement.dataset.userVote;
            if (knownUserVote && (knownUserVote === 'option_one' || knownUserVote === 'option_two')) {
                const currentVoteData = {
                    user_vote: knownUserVote,
                    option_one_votes: parseInt(postElement.dataset.optionOneVotes, 10),
                    option_two_votes: parseInt(postElement.dataset.optionTwoVotes, 10),
                    total_votes: parseInt(postElement.dataset.optionOneVotes, 10) + parseInt(postElement.dataset.optionTwoVotes, 10),
                };
                updateVoteUI(postId, currentVoteData.user_vote, currentVoteData);
                if (window.showToast) window.showToast(window.translations.js_error_already_voted, 'info');
                return;
            }

            const originalClickedButtonClasses = Array.from(clickedButton.classList);
            const originalOtherButtonClasses = Array.from(otherButton.classList);
            const totalVotesDisplayElement = postElement.querySelector('.flex.justify-between.items-center .flex.flex-col.items-center.gap-1 span.text-lg.font-semibold');
            const originalTotalVotesText = totalVotesDisplayElement ? totalVotesDisplayElement.textContent : (parseInt(postElement.dataset.optionOneVotes, 10) + parseInt(postElement.dataset.optionTwoVotes, 10)).toString();


            clickedButton.classList.add('voting-in-progress');
            clickedButton.disabled = true;
            otherButton.disabled = true;


            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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

                if (response.ok) {
                    updateVoteUI(postId, responseData.user_vote, responseData);
                    if (window.showToast && responseData.message && responseData.message.toLowerCase().includes('successfully')) {
                        window.showToast(responseData.message, 'success');
                    }
                } else {
                    const errorMessage = responseData.message || responseData.error || (responseData.errors ? Object.values(responseData.errors).join(', ') : `Vote failed (HTTP ${response.status})`);
                    if (window.showToast) {
                        window.showToast(errorMessage, response.status === 409 ? 'info' : 'error');
                    }
                    if (response.status === 409 && responseData.user_vote && typeof responseData.option_one_votes !== 'undefined') {
                        updateVoteUI(postId, responseData.user_vote, responseData);
                    } else {
                        clickedButton.className = originalClickedButtonClasses.join(' ');
                        otherButton.className = originalOtherButtonClasses.join(' ');
                        if (totalVotesDisplayElement) totalVotesDisplayElement.textContent = originalTotalVotesText;
                    }
                }
            } catch (error) {
                console.error('Error voting (catch):', error);
                if (window.showToast) {
                    window.showToast(window.translations.js_vote_failed_connection, 'error');
                }
                clickedButton.className = originalClickedButtonClasses.join(' ');
                otherButton.className = originalOtherButtonClasses.join(' ');
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
                console.error(`updateVoteUI: Post element with ID 'post-${postId}' not found.`);
                return;
            }

            postElement.dataset.userVote = userVotedOption;
            postElement.dataset.optionOneVotes = voteData.option_one_votes;
            postElement.dataset.optionTwoVotes = voteData.option_two_votes;

            const totalVotesDisplayElement = postElement.querySelector('.flex.justify-between.items-center .flex.flex-col.items-center.gap-1 span.text-lg.font-semibold');
            if (totalVotesDisplayElement) {
                totalVotesDisplayElement.textContent = voteData.total_votes;
            }

            const totalVotes = parseInt(voteData.total_votes, 10) || 0;
            const optionOneVotes = parseInt(voteData.option_one_votes, 10) || 0;
            const optionTwoVotes = parseInt(voteData.option_two_votes, 10) || 0;
            const percentOne = totalVotes > 0 ? Math.round((optionOneVotes / totalVotes) * 100) : 0;
            const percentTwo = totalVotes > 0 ? Math.round((optionTwoVotes / totalVotes) * 100) : 0;

            const imageContainers = postElement.querySelectorAll('.image-loader-container[data-image-option]');
            imageContainers.forEach(container => {
                const option = container.dataset.imageOption;
                const overlay = container.querySelector('.vote-result-overlay');
                const waterFill = container.querySelector('.water-fill');
                const percentageText = container.querySelector('.vote-percentage-text');

                if (!overlay || !waterFill || !percentageText) return;

                const isVotedOption = (option === userVotedOption);
                const percentage = (option === 'option_one') ? percentOne : percentTwo;

                overlay.classList.remove('opacity-0');
                overlay.classList.add('opacity-100');
                percentageText.textContent = `${percentage}%`;
                waterFill.style.height = `${percentage}%`;

                if (isVotedOption) {
                    container.classList.remove('is-monochrome');
                } else {
                    container.classList.add('is-monochrome');
                }
            });

            const optionOneButton = postElement.querySelector('button.vote-button[data-option="option_one"]');
            const optionTwoButton = postElement.querySelector('button.vote-button[data-option="option_two"]');

            if (optionOneButton && optionTwoButton) {
                const highlightClasses = ['bg-blue-800', 'text-white'];
                const defaultClasses = ['bg-white', 'border', 'border-gray-300'];

                [optionOneButton, optionTwoButton].forEach(button => {
                    button.classList.remove(...highlightClasses, ...defaultClasses, 'hover:bg-gray-50');
                });

                if (userVotedOption === 'option_one') {
                    optionOneButton.classList.add(...highlightClasses);
                    optionTwoButton.classList.add(...defaultClasses);
                } else if (userVotedOption === 'option_two') {
                    optionTwoButton.classList.add(...highlightClasses);
                    optionOneButton.classList.add(...defaultClasses);
                }

                // const votesLabel = window.translations.js_votes_label || 'votes';
                // optionOneButton.title = `${optionOneVotes} ${votesLabel}`;
                // optionTwoButton.title = `${optionTwoVotes} ${votesLabel}`;
            }
        }
    // }
</script>
