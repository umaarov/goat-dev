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
//    $postSlug = Str::slug($post->question, '-', 'en');
//    $postUrl = route('posts.show', ['post' => $post, 'slug' => $postSlug]);
    $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);
    $insightPreference = Auth::user()->ai_insight_preference ?? 'expanded';

    $totalVotes = $post->total_votes;
    $optionOneVotes = $post->option_one_votes;
    $optionTwoVotes = $post->option_two_votes;
    $percentOne = $totalVotes > 0 ? round(($optionOneVotes / $totalVotes) * 100) : 0;
    $percentTwo = $totalVotes > 0 ? round(($optionTwoVotes / $totalVotes) * 100) : 0;
    $hasVoted = !is_null($currentViewerVote);
@endphp
<article class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] overflow-hidden mb-4"
         id="post-{{ $post->id }}"
         style="content-visibility: auto; contain-intrinsic-size: 500px;"
         data-share-url="{{ $postUrl }}"
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
                 data-full-src="{{ $profilePic }}" loading="lazy" decoding="async"
                 width="40" height="40">
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
                          onsubmit="return confirm(@json(__('messages.confirm_delete_post_text')))">
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

    <div x-data="{
        isPanelVisible: false,
        isExpanded: false,
        showFeatureHint: false
     }"
         x-init="
        const preference = '{{ $insightPreference }}';
        if (preference !== 'hidden') { isPanelVisible = true; }
        if (preference === 'expanded') { isExpanded = true; }

        @if(Auth::check())
            if (!localStorage.getItem('seenAiInsightHint')) {
                // We show the hint only if the panel is visible on load
                if (preference !== 'hidden') {
                    setTimeout(() => { showFeatureHint = true }, 1500);
                }
            }
        @endif
     "
         class="pt-4 px-4 font-semibold text-center">

        <div>
            <h2 class="text-lg text-gray-800" style="font-size: inherit; font-weight: inherit; margin: 0; padding: 0;">
                {{ $post->question }}

                @if($post->ai_generated_context)
                    <button @click="isPanelVisible = !isPanelVisible"
                            class="transition-colors duration-200 focus:outline-none inline-block align-text-top"
                            :class="{ 'text-blue-600': isPanelVisible, 'text-gray-400 hover:text-blue-600': !isPanelVisible }"
                            :title="isPanelVisible ? 'Hide AI context' : 'Show AI context. You can change the default in Settings.'">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9.25 12.75a.75.75 0 001.5 0v-2.5a.75.75 0 00-1.5 0v2.5zM10 6a.75.75 0 01.75.75v.008a.75.75 0 01-1.5 0V6.75A.75.75 0 0110 6z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif
            </h2>
        </div>

        {{-- AI Context Panel --}}
        @if($post->ai_generated_context)
            <div x-show="isPanelVisible" x-transition class="text-sm font-normal text-left mt-4">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg">

                    @if(Auth::check())
                        <div x-show="showFeatureHint"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="bg-blue-100 border border-blue-200 rounded-md p-2.5 mb-4 text-sm"
                             style="display: none;">

                            <div class="flex items-center justify-between">
                                <p class="text-blue-800">
                                    <span class="font-bold">New!</span> You can now set the default view for these insights in
                                    <a href="{{ route('profile.edit') }}" class="font-bold underline hover:text-blue-900">Settings</a>.
                                </p>
                                <button @click="showFeatureHint = false; localStorage.setItem('seenAiInsightHint', 'true')"
                                        title="Dismiss"
                                        class="text-blue-600 hover:text-blue-800 ml-3">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>

                        </div>
                    @endif

                    <h3 class="flex items-center gap-2 text-xs font-bold text-blue-800 uppercase tracking-wider mb-2">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.898 20.573L16.5 21.75l-.398-1.177a3.375 3.375 0 00-2.496-2.496L12.25 18l1.177-.398a3.375 3.375 0 002.496-2.496L16.5 14.25l.398 1.177a3.375 3.375 0 002.496 2.496l1.177.398-1.177.398a3.375 3.375 0 00-2.496 2.496z" /></svg>
                        AI Insight
                    </h3>

                    <div class="relative transition-all duration-500 ease-in-out"
                         :class="{ 'max-h-24 overflow-hidden': !isExpanded, 'max-h-screen': isExpanded }">
                        <p class="text-gray-800 leading-relaxed">{!! nl2br(e($post->ai_generated_context)) !!}</p>

                        <div x-show="!isExpanded"
                             class="absolute bottom-0 left-0 w-full h-12 bg-gradient-to-t from-blue-50 to-transparent">
                        </div>
                    </div>

                    <button @click="isExpanded = !isExpanded"
                            class="text-blue-700 hover:underline text-xs font-bold mt-2">
                        <span x-text="isExpanded ? 'Show less' : 'Show more'"></span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Main Image Display --}}
    <div class="grid grid-cols-2 gap-4 p-4">
        {{-- OPTION ONE IMAGE --}}
        <a href="{{ $postUrl }}" class="block post-link-for-prerender">
            <div class="relative image-loader-container aspect-square rounded-md overflow-hidden bg-gray-100 bg-cover bg-center {{ $hasVoted && $currentViewerVote !== 'option_one' ? 'is-monochrome' : '' }}"
                 data-image-option="option_one"
                 @if($post->option_one_image_lqip) style="background-image: url('{{ $post->option_one_image_lqip }}');" @endif>

                @if($post->option_one_image)
                    <img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                         data-src="{{ asset('storage/' . $post->option_one_image) }}"
                         alt="{{ $post->question }} - {{ $post->option_one_title }}"
                         class="progressive-image h-full w-full object-cover object-center cursor-pointer zoomable-image transition-all duration-300"
                         decoding="async">
                @else
                    <div class="bg-gray-200 rounded-full p-2">
                        <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                @endif

                {{-- Vote Result Overlay --}}
                <div class="vote-result-overlay absolute inset-0 flex items-center justify-center pointer-events-none {{ $hasVoted ? 'opacity-100' : 'opacity-0' }} transition-opacity duration-300">
                    <div class="water-fill absolute bottom-0 left-0 w-full bg-blue-800 bg-opacity-70 transition-all duration-700 ease-in-out" style="height: {{ $hasVoted ? $percentOne : 0 }}%;"></div>
                    <span class="vote-percentage-text relative text-white text-4xl font-bold" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.7);">{{ $hasVoted ? "{$percentOne}%" : '0%' }}</span>
                </div>
            </div>
        </a>

        {{-- OPTION TWO IMAGE --}}
        <a href="{{ $postUrl }}" class="block post-link-for-prerender">
            <div class="relative image-loader-container aspect-square rounded-md overflow-hidden bg-gray-100 bg-cover bg-center {{ $hasVoted && $currentViewerVote !== 'option_two' ? 'is-monochrome' : '' }}"
                 data-image-option="option_two"
                 @if($post->option_two_image_lqip) style="background-image: url('{{ $post->option_two_image_lqip }}');" @endif>

                @if($post->option_two_image)
                    <img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                         data-src="{{ asset('storage/' . $post->option_two_image) }}"
                         alt="{{ $post->question }} - {{ $post->option_two_title }}"
                         class="progressive-image h-full w-full object-cover object-center cursor-pointer zoomable-image transition-all duration-300"
                         decoding="async">
                @else
                    <div class="bg-gray-200 rounded-full p-2">
                        <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                @endif

                {{-- Vote Result Overlay --}}
                <div class="vote-result-overlay absolute inset-0 flex items-center justify-center pointer-events-none {{ $hasVoted ? 'opacity-100' : 'opacity-0' }} transition-opacity duration-300">
                    <div class="water-fill absolute bottom-0 left-0 w-full bg-blue-800 bg-opacity-70 transition-all duration-700 ease-in-out" style="height: {{ $hasVoted ? $percentTwo : 0 }}%;"></div>
                    <span class="vote-percentage-text relative text-white text-4xl font-bold" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.7);">{{ $hasVoted ? "{$percentTwo}%" : '0%' }}</span>
                </div>
            </div>
        </a>
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
            {{--            <p class="button-text-truncate">{{ $post->option_one_title }} {{ $showPercentagesOnButtons ? "($percentOne%)" : "" }}</p>--}}
            <p class="button-text-truncate">{{ $post->option_one_title }}</p>
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
            {{--            <p class="button-text-truncate">{{ $post->option_two_title }} {{ $showPercentagesOnButtons ? "($percentTwo%)" : "" }}</p>--}}
            <p class="button-text-truncate">{{ $post->option_two_title }}</p>
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


    @if(!empty(trim((string) $post->ai_generated_tags)))
        @php
            $tags = collect(explode(',', $post->ai_generated_tags))
                    ->map(fn($tag) => trim($tag))
                    ->filter()
                    ->unique();
        @endphp
        <div class="px-4 pb-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1.5">
            @foreach($tags->take(7) as $tag)
                <a href="{{ route('search', ['q' => $tag]) }}" class="block bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-full hover:bg-blue-100 hover:text-blue-800 transition-colors duration-200">#{{ \Illuminate\Support\Str::of($tag)->camel()->ucfirst() }}</a>
            @endforeach
        </div>
    @endif


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

    <div id="comments-section-{{ $post->id }}" class="comments-section hidden">
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

                    <div id="typing-indicator-{{ $post->id }}" class="text-xs text-gray-500 italic h-5"></div>

                    <div class="flex justify-between">
                        <button type="button" onclick="toggleComments('{{ $post->id }}')"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm py-1 px-4 rounded-md">{{ __('messages.cancel_button') }}
                        </button>
                        {{--                        <button type="submit"--}}
                        {{--                                class="bg-blue-800 hover:bg-blue-900 text-white text-sm py-1 px-4 rounded-md">{{ __('messages.submit_comment_button') }}--}}
                        {{--                        </button>--}}
                        <button type="submit"
                                disabled
                                class="bg-blue-400 cursor-not-allowed text-white text-sm py-1 px-4 rounded-md transition-colors duration-300">
                            {{ __('messages.submit_comment_button') }}
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

<template id="comment-shimmer-template">
    <div class="shimmer-comment py-3">
        <div class="flex items-start space-x-3">
            <div class="shimmer-bg w-8 h-8 rounded-full flex-shrink-0"></div>
            <div class="flex-1 space-y-2 py-1">
                <div class="shimmer-bg h-4 rounded w-1/3"></div>
                <div class="shimmer-bg h-4 rounded w-5/6"></div>
                <div class="shimmer-bg h-3 rounded w-1/4 mt-2"></div>
            </div>
        </div>
    </div>
</template>

<x-shared-post-scripts />
