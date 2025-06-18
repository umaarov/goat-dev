@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;
    $showManagementOptions = $showManagementOptions ?? false;
    $profileOwnerToDisplay = $profileOwnerToDisplay ?? null;
    $isFirst = $isFirst ?? false;
    $currentViewerVote = $post->user_vote ?? null;

    $voteByProfileOwner = null;
    if ($profileOwnerToDisplay && isset($post->pivot, $post->pivot->vote_option) && $post->pivot->user_id == $profileOwnerToDisplay->id) {
        $voteByProfileOwner = $post->pivot->vote_option;
    }
    $highlightOptionForViewer = $currentViewerVote;
    $showPercentagesOnButtons = $currentViewerVote || $voteByProfileOwner;
    $showVotedByOwnerIcon = $profileOwnerToDisplay && !$showManagementOptions && $voteByProfileOwner;
    $postSlug = Str::slug($post->question, '-', 'en');
    $postUrl = route('posts.show', ['post' => $post, 'slug' => $postSlug]);
@endphp
@push('schema')
    <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "SocialMediaPosting",
          "headline": "{{ addslashes($post->question) }}",
          "url": "{{ $postUrl }}",
          "datePublished": "{{ $post->created_at->toIso8601String() }}",
          "author": {
            "@type": "Person",
            "name": "{{ '@' . $post->user->username }}",
            "url": "{{ route('profile.show', $post->user->username) }}"
          },
          @if($post->option_one_image || $post->option_two_image)
            "image": [
            @if($post->option_one_image)
                "{{ asset('storage/' . $post->option_one_image) }}"@if($post->option_two_image),@endif
            @endif
            @if($post->option_two_image)
                "{{ asset('storage/' . $post->option_two_image) }}"
            @endif
            ],
          @endif
          "interactionStatistic": [
          {
            "@type": "InteractionCounter",
            "interactionType": { "@type": "CommentAction" },
            "userInteractionCount": {{ $post->comments_count }}
          },
          {
            "@type": "InteractionCounter",
            "interactionType": { "@type": "LikeAction" },
            "userInteractionCount": {{ $post->total_votes }}
          }
         ],
         "publisher": {
            "@type": "Organization",
            "name": "GOAT.uz",
            "logo": {
                "@type": "ImageObject",
                "url": "{{ asset('images/icons/icon-512x512.png') }}"
         }
    }
}
    </script>
@endpush
<article class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4"
         id="post-{{ $post->id }}"
         data-option-one-title="{{ $post->option_one_title }}"
         data-option-two-title="{{ $post->option_two_title }}"
         data-option-one-votes="{{ $post->option_one_votes }}"
         data-option-two-votes="{{ $post->option_two_votes }}"
         data-user-vote="{{ $currentViewerVote ?? '' }}"
         @if($showVotedByOwnerIcon)
             data-profile-owner-username="{{ $profileOwnerToDisplay->username }}"
         data-profile-owner-vote-option="{{ $voteByProfileOwner }}"
    @endif
>
    <header class="p-4">
        <div class="flex">
            @php
                $profilePic = $post->user->profile_picture
                ? (Str::startsWith($post->user->profile_picture, ['http', 'https'])
                ? $post->user->profile_picture
                : asset('storage/' . $post->user->profile_picture))
                : asset('images/default-pfp.png');

                $isVerified = in_array($post->user->username, ['goat', 'umarov'])
            @endphp
            <img src="{{ $profilePic }}"
                 alt="{{ __('messages.profile.alt_profile_picture', ['username' => $post->user->username]) }}"
                 class="w-10 h-10 rounded-full border border-gray-300 cursor-pointer zoomable-image"
                 data-full-src="{{ $profilePic }}" loading="lazy" decoding="async">
            <div class="ml-3">
                <div class="flex items-center">
                    <a href="{{ route('profile.show', $post->user->username) }}"
                       class="font-medium text-gray-800 hover:underline">{{ '@' . $post->user->username }}</a>

                    @if($isVerified)
                        <span class="ml-1" title="{{ __('messages.profile.verified_account') }}">
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
{{--                <a href="{{ $postUrl }}" class="text-xs text-gray-500 hover:underline">--}}
{{--                    <time datetime="{{ $post->created_at->toIso8601String() }}">--}}
{{--                        {{ $post->created_at->diffForHumans() }}--}}
{{--                    </time>--}}
{{--                </a>--}}
            </div>
            @if ($showManagementOptions && Auth::check() && (int)Auth::id() === (int)$post->user_id)
                <div class="flex justify-end border-gray-200 pl-4 ml-auto">
                    <form action="{{ route('posts.destroy', $post) }}" method="POST"
                          onsubmit="return confirm('{{ __('messages.confirm_delete_post_text') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="bg-red-100 hover:bg-red-200 text-red-700 text-sm py-1 px-3 rounded-md">
                            {{ __('messages.delete_button') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </header>

    <div class="border-b w-full border-gray-200"></div>

    <div class="pt-4 px-4 font-semibold text-center">
{{--        <p class="text-lg text-gray-800">{{ $post->question }}</p>--}}
        <h2 class="text-lg text-gray-800" style="font-size: inherit; font-weight: inherit; margin: 0; padding: 0;">{{ $post->question }}</h2>
    </div>

    <div class="grid grid-cols-2 gap-4 p-4 h-52">
        <div class="rounded-md overflow-hidden">
            @if($post->option_one_image)
                @php $optionOneImageUrl = asset('storage/' . $post->option_one_image); @endphp
                <img src="{{ $optionOneImageUrl }}" alt="{{ $post->question }} - {{ $post->option_one_title }}"
                     class="h-full w-full object-cover object-center cursor-pointer zoomable-image"
                     data-full-src="{{ $optionOneImageUrl }}" @if(!$isFirst) loading="lazy" @endif decoding="async">
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
                @php $optionTwoImageUrl = asset('storage/' . $post->option_two_image); @endphp
                <img src="{{ asset('storage/' . $post->option_two_image) }}"
                     alt="{{ $post->question }} - {{ $post->option_two_title }}"
                     class="h-full w-full object-cover object-center cursor-pointer zoomable-image"
                     data-full-src="{{ $optionTwoImageUrl }}" @if(!$isFirst)  loading="lazy" @endif decoding="async">
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

    <div class="grid grid-cols-2 gap-4 px-4 pb-4">
        @php
            $totalVotes = $post->total_votes;
            $optionOneVotes = $post->option_one_votes;
            $optionTwoVotes = $post->option_two_votes;
            $percentOne = $totalVotes > 0 ? round(($optionOneVotes / $totalVotes) * 100) : 0;
            $percentTwo = $totalVotes > 0 ? round(($optionTwoVotes / $totalVotes) * 100) : 0;
            $hasVoted = Auth::check() && $post->user_vote;
            $isNotLoggedIn = !Auth::check();
        @endphp

        <button
            class="vote-button p-2 text-[16px] text-center rounded-md relative
                   {{ $highlightOptionForViewer === 'option_one' ? 'bg-blue-800 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' }}
                   {{ $isNotLoggedIn ? 'opacity-75 cursor-not-allowed' : '' }}"
            onclick="voteForOption('{{ $post->id }}', 'option_one')"
            data-option="option_one"
            @if($showPercentagesOnButtons) data-tooltip-show-count="true" @endif
            @if($showVotedByOwnerIcon && $voteByProfileOwner === 'option_one') data-tooltip-is-owner-choice="true" @endif
        >
            <p class="button-text-truncate">{{ $post->option_one_title }} {{ $showPercentagesOnButtons ? "($percentOne%)" : "" }}</p>
            @if($showVotedByOwnerIcon && $voteByProfileOwner === 'option_one')
                <span
                    class="absolute top-0 right-0 -mt-2 -mr-2 px-1.5 py-0.5 bg-indigo-500 text-white text-[9px] leading-none rounded-full shadow-md flex items-center justify-center pointer-events-none"
                    title="{{ __('messages.post_card.owner_voted_for_this_option', ['username' => $profileOwnerToDisplay->username]) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                              clip-rule="evenodd"/>
                    </svg>
                </span>
            @endif
        </button>

        <button
            class="vote-button p-2 text-[16px] text-center rounded-md relative
                   {{ $highlightOptionForViewer === 'option_two' ? 'bg-blue-800 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' }}
                   {{ $isNotLoggedIn ? 'opacity-75 cursor-not-allowed' : '' }}"
            onclick="voteForOption('{{ $post->id }}', 'option_two')"
            data-option="option_two"
            @if($showPercentagesOnButtons) data-tooltip-show-count="true" @endif
            @if($showVotedByOwnerIcon && $voteByProfileOwner === 'option_two') data-tooltip-is-owner-choice="true"
            @endif
        >
            <p class="button-text-truncate">{{ $post->option_two_title }} {{ $showPercentagesOnButtons ? "($percentTwo%)" : "" }}</p>
            @if($showVotedByOwnerIcon && $voteByProfileOwner === 'option_two')
                <span
                    class="absolute top-0 right-0 -mt-2 -mr-2 px-1.5 py-0.5 bg-indigo-500 text-white text-[9px] leading-none rounded-full shadow-md flex items-center justify-center pointer-events-none"
                    title="{{ __('messages.post_card.owner_voted_for_this_option', ['username' => $profileOwnerToDisplay->username]) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                              clip-rule="evenodd"/>
                    </svg>
                </span>
            @endif
        </button>
    </div>

    <div class="border-b w-full border-gray-200"></div>

    <div class="flex justify-between items-center px-8 py-3 text-sm text-gray-600">
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

        <div class="flex flex-col items-center gap-1">
            <span class="text-lg font-semibold">{{ $post->total_votes }}</span>
            <span>{{ __('messages.post_card.votes_label') }}</span>
        </div>

        <button class="flex flex-col items-center gap-1 cursor-pointer" onclick="sharePost('{{ $post->id }}')">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
            </div>
            <span>{{ $post->shares_count }}</span>
        </button>
    </div>

    <div id="comments-section-{{ $post->id }}" class="hidden">
        <div class="border-b border-gray-200"></div>

        @if (Auth::check())
            <div class="p-4 border-b border-gray-200 comment-form-container">
                <form id="comment-form-{{ $post->id }}"
                      onsubmit="submitComment('{{ $post->id }}', event)"
                      class="flex flex-col space-y-2">
                    @csrf
                    <input type="hidden" name="parent_id" value="">

                    <textarea name="content" rows="2" placeholder="{{ __('messages.add_comment_placeholder') }}"
                              required
                              class="w-full border border-gray-300 rounded-md p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>

                    <div id="reply-indicator-{{ $post->id }}" class="text-xs text-gray-500 hidden items-center">
                        <span></span>
                        <button type="button" onclick="cancelReply('{{ $post->id }}')" class="ml-2 text-red-500 hover:underline">
                            {{ __('messages.cancel_button') }}
                        </button>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" onclick="toggleComments('{{ $post->id }}')"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm py-1 px-4 rounded-md">{{ __('messages.cancel_button') }}
                        </button>
                        <button type="submit"
                                class="bg-blue-800 hover:bg-blue-900 text-white text-sm py-1 px-4 rounded-md">{{ __('messages.submit_comment_button') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('messages.comments_title') }}</h4>

            <div class="comments-list"></div>

            <div id="pagination-container-{{ $post->id }}" class="mt-4"></div>
        </div>
    </div>
</article>

<style>
    .comments-section {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease-in-out;
        opacity: 0;
    }

    .comments-section.active {
        max-height: 5000px;
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

    .comments-list {
        transition: opacity 0.3s ease;
        min-height: 50px;
    }

    .comment {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.8s ease;
        padding: 8px 0;
    }

    .comment.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .comments-list .comment {
        padding-bottom: 12px;
    }

    .comments-list .comment:last-child {
        /*border-bottom: none;*/
        padding-bottom: 0;
        /*margin-bottom: 0;*/
    }

    .comment.highlighted-comment {
        background-color: rgba(59, 130, 246, 0.1);
        border-radius: 8px;
    }

    .replies-container {
        margin-left: calc(2rem + 0.75rem);
        margin-top: 8px;
        transition: max-height 0.4s ease, opacity 0.3s ease;
        max-height: 5000px;
        overflow: hidden;
    }

    .replies-container.hidden {
        max-height: 0;
        margin-top: 0;
        opacity: 0;
    }
    .replies-container .comment {
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .replies-container .comment:last-child {
        padding-bottom: 0;
    }

    .view-replies-button {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }

    .view-replies-button .line {
        height: 1px;
        width: 2rem;
        background-color: #d1d5db;
        margin-right: 0.5rem;
        transition: width 0.3s ease;
    }

    .view-replies-button:hover .line {
        background-color: #6b7280;
    }

    .view-replies-button.active .line {
        width: 1rem;
    }

    .replies-container .comment:last-child {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 0;
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

    .highlight-post {
        animation: micro-lift 0.8s ease-out;
    }

    @keyframes micro-lift {
        0% {
            transform: translateY(1px);
        }
        100% {
            transform: translateY(0);
        }
    }

    .zoomable-image {
        cursor: pointer;
    }

    .button-text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }

    .vote-button.voting-in-progress {
        opacity: 0.7;
        cursor: wait;
    }

    .vote-button.voting-in-progress .button-text-truncate::after {
        content: ' ...';
        display: inline-block;
        animation: dot-dot-dot 1s infinite;
    }

    @keyframes dot-dot-dot {
        0%, 80%, 100% {
            opacity: 0;
        }
        40% {
            opacity: 1;
        }
    }

    @-webkit-keyframes dot-dot-dot {
        0%, 80%, 100% {
            opacity: 0;
        }
        40% {
            opacity: 1;
        }
    }

    .shimmer-bg {
        animation: shimmer 1.5s linear infinite;
        background-image: linear-gradient(to right, #e2e8f0 0%, #f8fafc 50%, #e2e8f0 100%);
        background-size: 200% 100%;
        background-color: #e2e8f0;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }
</style>

<script>
    if (typeof window.currentlyOpenCommentsId === 'undefined') {
        window.currentlyOpenCommentsId = null;
    }
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('scrollToPost'))
        scrollToPost({{ session('scrollToPost') }});
        @endif

        const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
        commentsSections.forEach(section => {
            if (!section.classList.contains('comments-section')) {
                section.classList.add('comments-section');
            }
            if (!section.classList.contains('hidden')) {
                section.classList.add('hidden');
            }
            section.classList.remove('active');
        });
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
        if (typeof text !== 'string') {
            return '';
        }
        const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
        const mentionRegex = /@([a-zA-Z0-9_]+)/g;

        let linkedText = text.replace(urlRegex, function(url, p1, p2, p3) {
            const fullUrl = p3 ? 'http://' + p3 : p1; //
            return `<a href="${fullUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all">${url}</a>`;
        });

        linkedText = linkedText.replace(mentionRegex, function(match, username) {
            return `<a href="/@${username}" class="text-blue-600 hover:underline">@${username}</a>`;
        });

        return linkedText;
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
            }, 10);
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
                // commentsContainer.innerHTML = '';
                if (page === 1) commentsContainer.innerHTML = '';

                if (data.comments.data.length === 0 && page === 1) {
                    commentsContainer.innerHTML = `<p class="text-sm text-gray-500 text-center">${window.translations.js_no_comments_be_first}</p>`;
                    return;
                }

                data.comments.data.forEach(comment => {
                    const commentDiv = createCommentElement(comment, postId, false);
                    commentsContainer.appendChild(commentDiv);

                    if (comment.flat_replies && comment.flat_replies.length > 0) {
                        const repliesContainer = commentDiv.querySelector('.replies-container');
                        comment.flat_replies.forEach(reply => {
                            const replyDiv = createCommentElement(reply, postId, true);
                            repliesContainer.appendChild(replyDiv);
                        });
                    }
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


        const linkedCommentContent = linkifyContent(commentData.content);

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
        if (isReply && commentData.parent && commentData.parent.user && commentData.parent_id !== commentData.root_comment_id) {
            const parentUsername = commentData.parent.user.username;
            if (!commentData.content.includes(`@${parentUsername}`)) {
                replyToHTML = `<a href="javascript:void(0)" onclick="scrollToComment('comment-${commentData.parent_id}')" class="text-blue-600 hover:underline mr-1 font-medium">@${parentUsername}</a>`;
            }
        }

        let repliesToggleHTML = '';
        if (!isReply && commentData.flat_replies && commentData.flat_replies.length > 0) {
            const replyCount = commentData.flat_replies.length;
            const viewText = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', replyCount);
            repliesToggleHTML = `<button class="view-replies-button font-semibold hover:underline" onclick="toggleRepliesContainer(this, 'comment-${commentData.id}')">${viewText}</button>`;
        }

        commentDiv.innerHTML = `
            <div class="flex items-start space-x-3">
                <img src="${profilePic}" alt="${altProfilePic}" loading="lazy" decoding="async" class="w-8 h-8 rounded-full flex-shrink-0 cursor-pointer zoomable-image" data-full-src="${profilePic}">
                <div class="flex-1">
                    <div class="text-sm">
                        <div class="flex items center">
                            <a href="/@${commentData.user.username}" class="font-semibold text-gray-900 hover:underline">${commentData.user.username}</a>
                                            ${verifiedIconHTML}
                        </div>
                        <span class="text-gray-800">${replyToHTML} ${linkedCommentContent}</span>
                    </div>
                    <div class="mt-1.5 flex items-center space-x-3 text-xs text-gray-500">
                        <small title="${commentData.created_at}">${formatTimestamp(commentData.created_at)}</small>
                        ${ {{ Auth::check() ? 'true' : 'false' }} ? `<div class="flex items-center">${likeButtonHTML}</div>` : ''}
                        <button onclick="prepareReply('${postId}', '${commentData.id}', '${commentData.user.username}')" class="font-semibold hover:underline" title="Reply to ${commentData.user.username}">${window.translations.reply_button_text || 'Reply'}</button>
                        ${repliesToggleHTML}
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
            <div class="replies-container"></div>
            `;
        return commentDiv;
    }

    function scrollToComment(commentId) {
        const element = document.getElementById(commentId);
        if (!element) {
            console.warn(`Could not find comment ${commentId} to scroll to.`);
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
        const isHidden = repliesContainer.classList.contains('hidden');

        if (isHidden) {
            repliesContainer.classList.remove('hidden');
            button.textContent = window.translations.hide_replies_text || 'Hide replies';
        } else {
            repliesContainer.classList.add('hidden');
            const replyCount = repliesContainer.children.length;
            button.textContent = (window.translations.view_replies_text || 'View replies (:count)').replace(':count', replyCount);
        }
    }

    function prepareReply(postId, commentId, username) {
        const form = document.getElementById(`comment-form-${postId}`);
        if (!form) return;

        form.elements.parent_id.value = commentId;

        const textarea = form.elements.content;
        textarea.value = `@${username} `;
        textarea.focus();

        const end = textarea.value.length;
        textarea.setSelectionRange(end, end);

        const indicator = document.getElementById(`reply-indicator-${postId}`);
        indicator.querySelector('span').textContent = `Replying to @${username}`;
        indicator.classList.remove('hidden');
        indicator.classList.add('flex');
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

    function submitComment(postId, event) {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const content = form.elements.content.value;
        const parentId = form.elements.parent_id.value;

        if (!content.trim()) {
            showToast(window.translations.js_comment_empty);
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = `<div class="inline-block animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div> ${window.translations.js_comment_button_submitting}`;

        const url = `/posts/${postId}/comments`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({content: content, parent_id: parentId || null})
        })
            .then(response => response.json())
            .then(data => {
                form.elements.content.value = '';
                submitButton.disabled = false;
                submitButton.innerHTML = window.translations.js_submit_comment_button;

                if (data.errors) {
                    showToast(window.translations.js_error_prefix + ' ' + Object.values(data.errors).join('\n'));
                    return;
                }
                const newComment = data.comment;
                const isReply = !!newComment.parent_id;
                newComment.user = data.comment.user || {
                        id: {{ Auth::id() ?? 'null' }},
                        username: '{{ Auth::check() ? Auth::user()->username : "" }}',
                        profile_picture: '{{ Auth::check() ? (Auth::user()->profile_picture ? (Str::startsWith(Auth::user()->profile_picture, ["http", "https"]) ? Auth::user()->profile_picture : asset("storage/" . Auth::user()->profile_picture)) : asset("images/default-pfp.png")) : "" }}'
                };


                const commentsSection = document.getElementById(`comments-section-${postId}`);
                const commentsContainer = commentsSection.querySelector('.comments-list');
                const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                if (noCommentsMessage && noCommentsMessage.textContent === window.translations.js_no_comments_be_first) {
                    commentsContainer.innerHTML = '';
                }

                const commentElement = createCommentElement(newComment, postId, isReply);

                if (isReply) {
                    const rootCommentId = newComment.root_comment_id || newComment.parent_id;
                    const repliesContainer = document.querySelector(`#comment-${rootCommentId} .replies-container`);

                    if (repliesContainer) {
                        repliesContainer.appendChild(commentElement);
                    } else {
                        loadComments(postId, 1);
                    }
                } else {
                    const commentsContainer = document.getElementById(`comments-section-${postId}`).querySelector('.comments-list');
                    const noCommentsMessage = commentsContainer.querySelector('p.text-center');
                    if (noCommentsMessage) noCommentsMessage.remove();
                    commentsContainer.insertBefore(commentElement, commentsContainer.firstChild);
                }

                setTimeout(() => commentElement.classList.add('visible'), 10);

                cancelReply(postId);
                form.elements.content.value = '';

                const commentCountElement = document.querySelector(`#post-${postId} .flex.justify-between.items-center.px-8.py-3 button:first-child span`);
                if (commentCountElement) {
                    commentCountElement.textContent = parseInt(commentCountElement.textContent) + 1;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                cancelReply(postId);
                submitButton.disabled = false;
                submitButton.innerHTML = window.translations.js_submit_comment_button;
                showToast(window.translations.js_failed_add_comment);
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

        console.log(`updateVoteUI called for post: ${postId}, userVote: ${userVotedOption}`, voteData);

        postElement.dataset.userVote = userVotedOption;
        postElement.dataset.optionOneVotes = voteData.option_one_votes;
        postElement.dataset.optionTwoVotes = voteData.option_two_votes;

        const totalVotesDisplayElement = postElement.querySelector('.flex.justify-between.items-center .flex.flex-col.items-center.gap-1 span.text-lg.font-semibold');
        if (totalVotesDisplayElement) {
            totalVotesDisplayElement.textContent = voteData.total_votes;
        } else {
            console.warn(`updateVoteUI: Total votes display element not found for post ${postId}.`);
        }

        const optionOneButton = postElement.querySelector('button.vote-button[data-option="option_one"]');
        const optionTwoButton = postElement.querySelector('button.vote-button[data-option="option_two"]');

        if (optionOneButton && optionTwoButton) {
            const optionOneTitleText = postElement.dataset.optionOneTitle || window.translations.js_option_1_default_title || 'Option 1';
            const optionTwoTitleText = postElement.dataset.optionTwoTitle || window.translations.js_option_2_default_title || 'Option 2';

            const totalVotes = parseInt(voteData.total_votes, 10);
            const optionOneVotes = parseInt(voteData.option_one_votes, 10);
            const optionTwoVotes = parseInt(voteData.option_two_votes, 10);

            const numOptionOneVotes = isNaN(optionOneVotes) ? 0 : optionOneVotes;
            const numOptionTwoVotes = isNaN(optionTwoVotes) ? 0 : optionTwoVotes;
            const numTotalVotes = isNaN(totalVotes) ? (numOptionOneVotes + numOptionTwoVotes) : totalVotes;


            const percentOne = numTotalVotes > 0 ? Math.round((numOptionOneVotes / numTotalVotes) * 100) : 0;
            const percentTwo = numTotalVotes > 0 ? Math.round((numOptionTwoVotes / numTotalVotes) * 100) : 0;

            optionOneButton.querySelector('.button-text-truncate').textContent = `${optionOneTitleText} (${percentOne}%)`;
            optionTwoButton.querySelector('.button-text-truncate').textContent = `${optionTwoTitleText} (${percentTwo}%)`;

            const highlightClasses = ['bg-blue-800', 'text-white'];
            const defaultClasses = ['bg-white', 'border', 'border-gray-300', 'hover:bg-gray-50'];
            const noHoverDefaultClasses = ['bg-white', 'border', 'border-gray-300'];

            [optionOneButton, optionTwoButton].forEach(button => {
                button.classList.remove(...highlightClasses, ...defaultClasses, ...noHoverDefaultClasses);
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

            const showPercentagesBasedOnCurrentState = userVotedOption || postElement.dataset.profileOwnerVoteOption;
            let votesLabelToUse = window.translations && window.translations.js_votes_label; // Check if window.translations itself exists
            let problemDetected = false;
            console.log("Value of window.translations.js_votes_label:", votesLabelToUse, "Type:", typeof votesLabelToUse);

            if (typeof votesLabelToUse !== 'string') {
                problemDetected = true;
                if (votesLabelToUse === undefined) {
                    console.warn("Tooltip 'votes' label (js_votes_label) is undefined in window.translations. Defaulting to 'votes'. Ensure 'messages.post_card.votes_label' is correctly defined in your Blade file and localization settings.");
                } else if (votesLabelToUse === null) {
                    console.warn("Tooltip 'votes' label (js_votes_label) is null in window.translations. Defaulting to 'votes'. Ensure 'messages.post_card.votes_label' is correctly defined.");
                } else {
                    console.warn(`Tooltip 'votes' label (js_votes_label) is not a string (type: ${typeof votesLabelToUse}, value: ${String(votesLabelToUse)}). Defaulting to 'votes'. Ensure 'messages.post_card.votes_label' provides a string.`);
                }
            } else if (votesLabelToUse.trim() === '' || votesLabelToUse.trim().toLowerCase() === 'undefined') {
                problemDetected = true;
                if (votesLabelToUse.trim().toLowerCase() === 'undefined') {
                    console.warn("The translation for 'js_votes_label' was the string 'undefined'. Defaulting to 'votes'. Please check your localization files (e.g., messages.post_card.votes_label).");
                } else {
                    console.warn("Tooltip 'votes' label (js_votes_label) is an empty string in window.translations. Defaulting to 'votes'. Ensure 'messages.post_card.votes_label' is correctly defined and not empty.");
                }
            }

            if (problemDetected) {
                votesLabelToUse = 'votes';
            }
            console.log("Final votesLabelToUse before setting title:", votesLabelToUse, "Type:", typeof votesLabelToUse);
            console.log("numOptionOneVotes:", numOptionOneVotes, "numOptionTwoVotes:", numOptionTwoVotes);

            if (showPercentagesBasedOnCurrentState) {
                optionOneButton.dataset.tooltipShowCount = "true";
                optionTwoButton.dataset.tooltipShowCount = "true";

                optionOneButton.title = `${numOptionOneVotes} ${votesLabelToUse}`;
                optionTwoButton.title = `${numOptionTwoVotes} ${votesLabelToUse}`;
                console.log("Set title for option one:", optionOneButton.title);
                console.log("Set title for option two:", optionTwoButton.title);
            } else {
                optionOneButton.removeAttribute('data-tooltip-show-count');
                optionTwoButton.removeAttribute('data-tooltip-show-count');
                optionOneButton.removeAttribute('title');
                optionTwoButton.removeAttribute('title');
            }

            optionOneButton.dataset.tooltipShowCount = "true";
            optionTwoButton.dataset.tooltipShowCount = "true";
        } else {
            console.warn(`updateVoteUI: Vote buttons not found for post ${postId}.`);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const commentsSections = document.querySelectorAll('[id^="comments-section-"]');
        commentsSections.forEach(section => {
            section.classList.add('comments-section');
            if (!section.classList.contains('hidden')) {
                section.classList.add('hidden');
            }
            const formContainer = section.querySelector('form')?.closest('div');
            if (formContainer && !formContainer.classList.contains('comment-form-container')) {
                formContainer.classList.add('comment-form-container');
            }
        });
    });
</script>
