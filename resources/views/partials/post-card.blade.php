@php use Illuminate\Support\Str; @endphp
<article class="post-card" id="post-{{ $post->id }}">
    <header class="post-header">
        @php
            $profilePic = $post->user->profile_picture
            ? (Str::startsWith($post->user->profile_picture, ['http', 'https'])
            ? $post->user->profile_picture
            : asset('storage/' . $post->user->profile_picture))
            : asset('images/default-pfp.png'); // Provide a default PFP path
        @endphp
        <img src="{{ $profilePic }}" alt="{{ $post->user->username }}'s profile picture">
        <div>
            <a href="{{ route('profile.show', $post->user->username) }}"
               style="font-weight: bold; text-decoration:none; color: inherit;">{{ $post->user->username }}</a><br>
            <small title="{{ $post->created_at->format('Y-m-d H:i:s') }}">{{ $post->created_at->diffForHumans()
                }}</small>
        </div>
    </header>

    <p style="font-size: 1.1em; margin-bottom: 1em;">{{ $post->question }}</p>

    <div class="post-options">
        <div class="option-1">
            @if($post->option_one_image)
                <img src="{{ asset('storage/' . $post->option_one_image) }}" alt="Option 1 Image">
            @endif
            <p>{{ $post->option_one_title }}</p>
            @auth
                @if(!$post->user_vote)
                    <form action="{{ route('posts.vote', $post) }}" method="POST" style="display: inline;">
                        @csrf
                        <input type="hidden" name="option" value="option_one">
                        <button type="submit">Vote</button>
                    </form>
                @endif
            @endauth
        </div>
        <div class="option-2">
            @if($post->option_two_image)
                <img src="{{ asset('storage/' . $post->option_two_image) }}" alt="Option 2 Image">
            @endif
            <p>{{ $post->option_two_title }}</p>
            @auth
                @if(!$post->user_vote)
                    <form action="{{ route('posts.vote', $post) }}" method="POST" style="display: inline;">
                        @csrf
                        <input type="hidden" name="option" value="option_two">
                        <button type="submit">Vote</button>
                    </form>
                @endif
            @endauth
        </div>
    </div>

    @if ($post->user_vote || (Auth::check() && Auth::id() === $post->user_id) || $post->total_votes > 0)
        @php
            $totalVotes = $post->total_votes;
            $optionOneVotes = $post->option_one_votes;
            $optionTwoVotes = $post->option_two_votes;
            $percentOne = $totalVotes > 0 ? round(($optionOneVotes / $totalVotes) * 100) : 0;
            $percentTwo = $totalVotes > 0 ? round(($optionTwoVotes / $totalVotes) * 100) : 0;
            if ($totalVotes > 0 && $percentOne + $percentTwo !== 100) {
            $percentTwo = 100 - $percentOne;
            }
        @endphp
        <div style="margin-top: 1em; font-size: 0.9em;">
            <p style="margin-bottom: 0.3em;">
                {{ $post->option_one_title }}: {{ $optionOneVotes }} votes ({{ $percentOne }}%)
                @if($post->user_vote === 'option_one')
                    <strong>(Your Vote)</strong>
                @endif
            </p>
            <div class="vote-bar-container">
                <div class="vote-bar vote-bar-1" style="width: {{ $percentOne }}%;" role="progressbar"
                     aria-valuenow="{{ $percentOne }}" aria-valuemin="0" aria-valuemax="100">{{ $percentOne }}%
                </div>
            </div>

            <p style="margin-bottom: 0.3em; margin-top: 0.5em;">
                {{ $post->option_two_title }}: {{ $optionTwoVotes }} votes ({{ $percentTwo }}%)
                @if($post->user_vote === 'option_two')
                    <strong>(Your Vote)</strong>
                @endif
            </p>
            <div class="vote-bar-container">
                <div class="vote-bar vote-bar-2" style="width: {{ $percentTwo }}%;" role="progressbar"
                     aria-valuenow="{{ $percentTwo }}" aria-valuemin="0" aria-valuemax="100">{{ $percentTwo }}%
                </div>
            </div>
        </div>
    @endif

    <div class="post-stats">
        Total Votes: {{ $post->total_votes }} | Comments: {{ $post->comments_count }}
    </div>

    @auth
        <div class="post-comment-form" style="margin-top: 1.5em;">
            <form action="{{ route('comments.store', $post) }}" method="POST">
                @csrf
                <textarea name="content" rows="2" placeholder="Write a comment..." required></textarea>
                <button type="submit">Comment</button>
            </form>
        </div>
    @endauth

    <div class="post-comments">
        @if($post->relationLoaded('comments') && $post->comments->count() > 0)
            <h4>Comments</h4>
            @foreach($post->comments as $comment)
                <div class="comment" id="comment-{{ $comment->id }}">
                    <div class="comment-header">
                        @php
                            $commenterPfp = $comment->user->profile_picture
                            ? (Str::startsWith($comment->user->profile_picture, ['http', 'https'])
                            ? $comment->user->profile_picture
                            : asset('storage/' . $comment->user->profile_picture))
                            : asset('images/default-pfp.png');
                        @endphp
                        <img src="{{ $commenterPfp }}" alt="{{ $comment->user->username }}'s profile picture">
                        <a href="{{ route('profile.show', $comment->user->username) }}"
                           style="font-weight: bold; text-decoration:none; color: inherit;">{{ $comment->user->username }}</a>
                        <small title="{{ $comment->created_at->format('Y-m-d H:i:s') }}">{{
                    $comment->created_at->diffForHumans() }}</small>

                        @if (Auth::check() && (Auth::id() === $comment->user_id || Auth::id() === $post->user_id))
                            <div class="comment-actions">
                                {{-- Edit could be AJAX later --}}
                                {{--
                                <button>Edit</button>
                                --}}
                                <form action="{{ route('comments.destroy', $comment) }}" method="POST"
                                      style="display: inline;"
                                      onsubmit="return confirm('Delete this comment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            style="background:none; color:red; border:none; padding:0; font-size:0.9em; cursor:pointer;">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <p>{{ $comment->content }}</p>
                </div>
            @endforeach
        @elseif($post->comments_count > 0)
            <button>Load Comments</button>
        @endif
    </div>

    @if (Auth::check() && Auth::id() === $post->user_id && request()->routeIs('profile.show'))
        <div class="post-actions" style="border-top: 1px solid #eee; padding-top: 1em; margin-top: 1em;">
            @if($post->total_votes === 0)
                <a href="{{ route('posts.edit', $post) }}" class="button-link"
                   style="background-color: #ffc107; color: #212529;">Edit</a>
            @else
                <small>(Cannot edit post with votes)</small>
            @endif
            <form action="{{ route('posts.destroy', $post) }}" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this post? This cannot be undone.');"
                  style="margin: 0;">
                @csrf
                @method('DELETE')
                <button type="submit" style="background-color: #dc3545;">Delete</button>
            </form>
        </div>
    @endif

</article>
