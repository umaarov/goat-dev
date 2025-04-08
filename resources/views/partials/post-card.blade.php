@php use Illuminate\Support\Str; @endphp
<article class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 mb-4" id="post-{{ $post->id }}">
    <header class="flex items-center p-4 border-b border-gray-200">
        @php
            $profilePic = $post->user->profile_picture
            ? (Str::startsWith($post->user->profile_picture, ['http', 'https'])
            ? $post->user->profile_picture
            : asset('storage/' . $post->user->profile_picture))
            : asset('images/default-pfp.png');
        @endphp
        <img src="{{ $profilePic }}" alt="{{ $post->user->username }}'s profile picture"
             class="w-10 h-10 rounded-full mr-3">
        <div>
            <a href="{{ route('profile.show', $post->user->username) }}"
               class="font-medium text-gray-800 hover:underline">{{ $post->user->username }}</a>
            <p class="text-xs text-gray-500"
               title="{{ $post->created_at->format('Y-m-d H:i:s') }}">{{ $post->created_at->diffForHumans() }}</p>
        </div>
    </header>

    <div class="px-4 py-3 border-b border-gray-200">
        <p class="text-gray-800 font-medium text-center">{{ $post->question }}</p>
    </div>

    <div class="grid grid-cols-2 gap-4 p-4 border-b border-gray-200">
        <div class="border rounded-lg overflow-hidden">
            @if($post->option_one_image)
                <div class="bg-gray-100 flex justify-center">
                    <img src="{{ asset('storage/' . $post->option_one_image) }}" alt="Option 1 Image"
                         class="h-40 object-cover object-center">
                </div>
            @endif
            <div class="p-3 text-center bg-blue-700 text-white">
                <p class="font-medium">{{ $post->option_one_title }}</p>
                @php
                    $totalVotes = $post->total_votes;
                    $optionOneVotes = $post->option_one_votes;
                    $percentOne = $totalVotes > 0 ? round(($optionOneVotes / $totalVotes) * 100) : 0;
                @endphp
                <p class="text-sm">({{ $percentOne }}%)</p>
                @auth
                    @if(!$post->user_vote)
                        <form action="{{ route('posts.vote', $post) }}" method="POST">
                            @csrf
                            <input type="hidden" name="option" value="option_one">
                            <button type="submit"
                                    class="mt-2 bg-blue-800 hover:bg-blue-900 text-white text-xs py-1 px-3 rounded">Vote
                            </button>
                        </form>
                    @endif
                @endauth
            </div>
        </div>

        <div class="border rounded-lg overflow-hidden">
            @if($post->option_two_image)
                <div class="bg-gray-100 flex justify-center">
                    <img src="{{ asset('storage/' . $post->option_two_image) }}" alt="Option 2 Image"
                         class="h-40 object-cover object-center">
                </div>
            @endif
            <div class="p-3 text-center bg-red-700 text-white">
                <p class="font-medium">{{ $post->option_two_title }}</p>
                @php
                    $totalVotes = $post->total_votes;
                    $optionTwoVotes = $post->option_two_votes;
                    $percentTwo = $totalVotes > 0 ? round(($optionTwoVotes / $totalVotes) * 100) : 0;
                @endphp
                <p class="text-sm">({{ $percentTwo }}%)</p>
                @auth
                    @if(!$post->user_vote)
                        <form action="{{ route('posts.vote', $post) }}" method="POST">
                            @csrf
                            <input type="hidden" name="option" value="option_two">
                            <button type="submit"
                                    class="mt-2 bg-red-800 hover:bg-red-900 text-white text-xs py-1 px-3 rounded">Vote
                            </button>
                        </form>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <div class="flex justify-between items-center px-4 py-3 border-b border-gray-200 text-sm text-gray-600">
        <div class="flex items-center space-x-2">
            <span class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                </svg>
                {{ $post->total_votes }} votes
            </span>
        </div>
        <div class="flex items-center space-x-2">
            <span class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                {{ $post->comments_count }}
            </span>
            <span class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                0
            </span>
        </div>
    </div>

    @auth
        <div class="p-4 border-b border-gray-200">
            <form action="{{ route('comments.store', $post) }}" method="POST" class="flex flex-col space-y-2">
                @csrf
                <textarea name="content" rows="2" placeholder="Write a comment..." required
                          class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                <button type="submit"
                        class="self-end bg-blue-600 hover:bg-blue-700 text-white text-sm py-1 px-4 rounded">Comment
                </button>
            </form>
        </div>
    @endauth

    <div class="p-4">
        @if(/* $post->relationLoaded('comments') && */ $post->comments->count() > 0)
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
                                <form action="{{ route('comments.destroy', $comment) }}" method="POST" class="inline"
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
            <button class="text-blue-500 hover:text-blue-700 text-sm font-medium">Load Comments</button>
        @endif
    </div>

    @if (Auth::check() && Auth::id() === $post->user_id && request()->routeIs('profile.show'))
        <div class="flex justify-end space-x-2 p-4 border-t border-gray-200">
            @if($post->total_votes === 0)
                <a href="{{ route('posts.edit', $post) }}"
                   class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm py-1 px-3 rounded">Edit</a>
            @else
                <small class="text-gray-500 text-xs self-center">(Cannot edit post with votes)</small>
            @endif
            <form action="{{ route('posts.destroy', $post) }}" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this post? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 text-sm py-1 px-3 rounded">
                    Delete
                </button>
            </form>
        </div>
    @endif
</article>
