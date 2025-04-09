@php use Illuminate\Support\Facades\Auth;use Illuminate\Support\Str; @endphp
<article class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4"
         id="post-{{ $post->id }}">
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
                <form action="{{ route('comments.store', $post) }}" method="POST" class="flex flex-col space-y-2">
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
                                    <form action="{{ route('comments.destroy', $comment) }}" method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Delete this comment?');">
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
    // Toggle comments visibility
    function toggleComments(postId) {
        const commentsSection = document.getElementById(`comments-section-${postId}`);
        if (commentsSection.classList.contains('hidden')) {
            commentsSection.classList.remove('hidden');
        } else {
            commentsSection.classList.add('hidden');
        }
    }

    // Vote function
    function voteForOption(postId, option) {
        // For authenticated users, submit the form
        if ({{ Auth::check() ? 'true' : 'false' }}) {
            // Create a form dynamically
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ url("/posts") }}/' + postId + '/vote';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';

            // Add option
            const optionInput = document.createElement('input');
            optionInput.type = 'hidden';
            optionInput.name = 'option';
            optionInput.value = option;

            // Append to document and submit
            form.appendChild(csrfToken);
            form.appendChild(optionInput);
            document.body.appendChild(form);
            form.submit();
        } else {
            // Redirect to login for non-authenticated users
            window.location.href = '{{ route("login") }}';
        }
    }

    // Share post function
    function sharePost(postId) {
        // Get the current URL
        const url = window.location.origin + '/posts/' + postId;

        // Check if the browser supports the clipboard API
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url)
                .then(() => {
                    alert('Link copied to clipboard!');
                })
                .catch(err => {
                    console.error('Could not copy text: ', err);
                });
        } else {
            // Fallback for browsers that don't support clipboard API
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.style.position = 'fixed';  // Prevent scrolling to bottom
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
