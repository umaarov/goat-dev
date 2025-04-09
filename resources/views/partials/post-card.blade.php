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
    <div id="comments-section-{{ $post->id }}" class="hidden">
        <!-- Horizontal line -->
        <div class="border-b border-gray-200"></div>

        <!-- Comment form for authenticated users -->
        @auth
            <div class="p-4 border-b border-gray-200">
                <form id="comment-form-{{ $post->id }}" onsubmit="submitComment('{{ $post->id }}', event)"
                      class="flex flex-col space-y-2">
                    @csrf
                    <textarea name="content" rows="2" placeholder="Write a comment..." required
                              class="w-full border border-gray-300 rounded-md p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    <div class="flex justify-between">
                        <button type="button" onclick="toggleComments('{{ $post->id }}')"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm py-1 px-4 rounded-md">Close
                        </button>
                        <button type="submit"
                                class="bg-blue-800 hover:bg-blue-900 text-white text-sm py-1 px-4 rounded-md">Comment
                        </button>
                    </div>
                </form>
            </div>
        @endauth

        <!-- Comments list -->
        <div class="p-4">
            @if($post->comments && $post->comments->count() > 0)
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Comments</h4>
                @foreach($post->comments as $comment)
                    <div class="comment mb-3 border-b border-gray-200 pb-3" id="comment-{{ $comment->id }}">
                        <div class="flex items-center mb-2">
                            @php
                                $commenterPfp = $comment->user->profile_picture
                                ? (Str::startsWith($comment->user->profile_picture, ['http', 'https'])
                                ? $comment->user->profile_picture
                                : asset('storage/' . $comment->user->profile_picture))
                                : asset('images/default-pfp.png');
                            @endphp
                            <img src="{{ $commenterPfp }}" alt="{{ $comment->user->username }}'s profile picture"
                                 class="w-8 h-8 rounded-full mr-2">
                            <div>
                                <div class="flex items-center">
                                    <a href="{{ route('profile.show', $comment->user->username) }}"
                                       class="text-sm font-medium text-gray-800 hover:underline">{{ $comment->user->username }}</a>
                                    <span class="mx-1 text-gray-400">·</span>
                                    <small class="text-xs text-gray-500"
                                           title="{{ $comment->created_at->format('Y-m-d H:i:s') }}">{{ $comment->created_at->diffForHumans() }}</small>
                                </div>
                            </div>

                            @if (Auth::check() && (Auth::id() === $comment->user_id || Auth::id() === $post->user_id))
                                <div class="ml-auto">
                                    <form onsubmit="deleteComment('{{ $comment->id }}', event)" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-500 text-xs">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <p class="text-sm text-gray-700 pl-10">{{ $comment->content }}</p>
                    </div>
                @endforeach
            @elseif($post->comments_count > 0)
                <div class="text-center">
                    <button class="text-blue-500 hover:text-blue-700 text-sm font-medium">Load Comments</button>
                </div>
            @else
                <p class="text-sm text-gray-500 text-center">No comments yet. Be the first to comment!</p>
            @endif
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

<script>

    function submitComment(postId, event) {
        event.preventDefault();
        const form = event.target;
        const content = form.elements.content.value;
        const url = `{{ url('/posts') }}/${postId}/comments`;
        const csrfToken = '{{ csrf_token() }}';

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
                if (data.errors) {
                    alert('Error: ' + Object.values(data.errors).join('\n'));
                    return;
                }

                form.elements.content.value = '';

                const commentsSection = document.querySelector(`#comments-section-${postId}`);

                const commentForm = commentsSection.querySelector('form#comment-form-' + postId);
                const commentsContainer = commentForm ?
                    commentForm.closest('div').nextElementSibling :
                    commentsSection.querySelector('.p-4');

                const commentDiv = document.createElement('div');
                commentDiv.className = 'comment mb-3 border-b border-gray-200 pb-3';
                commentDiv.id = 'comment-' + data.comment.id;

                const profilePic = data.comment.user.profile_picture
                    ? (data.comment.user.profile_picture.startsWith('http')
                        ? data.comment.user.profile_picture
                        : '{{ asset("storage") }}/' + data.comment.user.profile_picture)
                    : '{{ asset("images/default-pfp.png") }}';

                commentDiv.innerHTML = `
    <div class="flex items-center mb-2">
        <img src="${profilePic}" alt="${data.comment.user.username}'s profile picture" class="w-8 h-8 rounded-full mr-2">
        <div>
            <div class="flex items-center">
                <a href="{{ url('/@') }}${data.comment.user.username}" class="text-sm font-medium text-gray-800 hover:underline">${data.comment.user.username}</a>
                <span class="mx-1 text-gray-400">·</span>
                <small class="text-xs text-gray-500" title="${data.comment.created_at}">Just now</small>
            </div>
        </div>
        <div class="ml-auto">
            <form onsubmit="deleteComment('${data.comment.id}', event)" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-gray-400 hover:text-red-500 text-xs">Delete</button>
            </form>
        </div>
    </div>
    <p class="text-sm text-gray-700 pl-10">${data.comment.content}</p>
    `;

                const existingComments = commentsContainer.querySelectorAll('.comment');

                if (existingComments.length === 0) {
                    if (commentsContainer.querySelector('p.text-center')) {
                        commentsContainer.innerHTML = '';
                    }

                    const existingHeading = commentsContainer.querySelector('h4.text-sm.font-semibold');
                    if (!existingHeading) {
                        const heading = document.createElement('h4');
                        heading.className = 'text-sm font-semibold text-gray-700 mb-3';
                        heading.textContent = 'Comments';
                        commentsContainer.appendChild(heading);
                    }
                }

                const firstComment = commentsContainer.querySelector('.comment');
                if (firstComment) {
                    commentsContainer.insertBefore(commentDiv, firstComment);
                } else {
                    commentsContainer.appendChild(commentDiv);
                }


                const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                commentCountElement.textContent = parseInt(commentCountElement.textContent) + 1;

                const headings = commentsSection.querySelectorAll('h4.text-sm.font-semibold');
                if (headings.length > 1) {
                    for (let i = 1; i < headings.length; i++) {
                        headings[i].remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to add comment. Please try again.');
            });
    }

    function toggleComments(postId) {
        const commentsSection = document.getElementById(`comments-section-${postId}`);
        if (commentsSection.classList.contains('hidden')) {
            commentsSection.classList.remove('hidden');
        } else {
            commentsSection.classList.add('hidden');
        }
    }

    function deleteComment(commentId, event) {
        event.preventDefault();

        if (!confirm('Delete this comment?')) {
            return;
        }

        const url = `{{ url('/comments') }}/${commentId}`;
        const csrfToken = '{{ csrf_token() }}';

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
                    return;
                }

                // Find and remove the comment element
                const commentElement = document.getElementById('comment-' + commentId);
                const postId = commentElement.closest('[id^="post-"]').id.split('-')[1];
                const commentsContainer = document.querySelector(`#comments-section-${postId} .p-4`);

                commentElement.remove();

                // Update the comment count
                const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                const currentCount = parseInt(commentCountElement.textContent);
                commentCountElement.textContent = currentCount - 1;

                // If there are no more comments, show the "no comments" message
                const remainingComments = commentsContainer.querySelectorAll('.comment');
                if (remainingComments.length === 0) {
                    // Remove the heading
                    const heading = commentsContainer.querySelector('h4.text-sm.font-semibold');
                    if (heading) {
                        heading.remove();
                    }

                    // Add the "no comments" message
                    commentsContainer.innerHTML = '<p class="text-sm text-gray-500 text-center">No comments yet. Be the first to comment!</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete comment. Please try again.');
            });
    }

    function voteForOption(postId, option) {
        // For authenticated users, submit the form
        if ({{ Auth::check() ? 'true' : 'false' }}) {
            const url = '{{ url("/posts") }}/' + postId + '/vote';
            const csrfToken = '{{ csrf_token() }}';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({option: option})
            })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    // Update the buttons and percentages
                    const post = document.getElementById('post-' + postId);
                    const optionOneBtn = post.querySelector('.grid.grid-cols-2.gap-4.px-4.pb-4 button:first-child');
                    const optionTwoBtn = post.querySelector('.grid.grid-cols-2.gap-4.px-4.pb-4 button:last-child');

                    // Reset button styles
                    optionOneBtn.className = 'p-3 text-center rounded-md bg-white border border-gray-300 hover:bg-gray-50';
                    optionTwoBtn.className = 'p-3 text-center rounded-md bg-white border border-gray-300 hover:bg-gray-50';

                    // Apply selected style to the voted option
                    if (option === 'option_one') {
                        optionOneBtn.className = 'p-3 text-center rounded-md bg-blue-800 text-white';
                    } else {
                        optionTwoBtn.className = 'p-3 text-center rounded-md bg-blue-800 text-white';
                    }

                    // Update the text with percentages
                    const percentOne = data.total_votes > 0 ? Math.round((data.option_one_votes / data.total_votes) * 100) : 0;
                    const percentTwo = data.total_votes > 0 ? Math.round((data.option_two_votes / data.total_votes) * 100) : 0;

                    optionOneBtn.querySelector('p').textContent = post.dataset.optionOneTitle + ' (' + percentOne + '%)';
                    optionTwoBtn.querySelector('p').textContent = post.dataset.optionTwoTitle + ' (' + percentTwo + '%)';

                    // Update the vote count
                    post.querySelector('.flex.justify-between.items-center.px-8.py-3 .flex.flex-col.items-center.gap-1 .text-lg').textContent = data.total_votes;
                    // alert('Vote registered successfully!');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to register vote. Please try again.');
                });
        } else {
            // Redirect to login for non-authenticated users
            window.location.href = '{{ route("login") }}';
        }
    }

    function sharePost(postId) {
        const url = window.location.origin + '/posts/' + postId;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(url)
                .then(() => {
                    alert('Link copied to clipboard!');
                })
                .catch(err => {
                    console.error('Could not copy text: ', err);
                });
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.style.position = 'fixed';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            try {
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            } catch (err) {
                console.error('Could not copy text: ', err);
            }

            document.body.removeChild(textarea);
        }
    }
</script>
