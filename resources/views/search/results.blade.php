@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('title', $queryTerm ? __('messages.search_results.title_with_query', ['queryTerm' => e($queryTerm)]) : __('messages.search_results.title_default'))
@section('meta_robots', 'noindex, follow')

@section('content')
    <div class="flex flex-col items-center justify-center w-full">
        <form action="{{ route('search') }}" method="GET" class="w-full max-w-xl mx-auto">
            <div class="relative">
                <input
                    type="search"
                    name="q"
                    value="{{ old('q', $queryTerm) }}"
                    placeholder="{{ __('messages.search_results.placeholder') }}"
                    class="w-full pl-10 pr-4 py-2 border-1 border-gray-300 rounded-2xl transition duration-150 ease-in-out"
                    autocomplete="off"
                />
                <button type="submit"
                        class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-blue-600">
                    <svg class="w-5 h-5" viewBox="0 -0.5 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                              d="M5.5 11.1455C5.49956 8.21437 7.56975 5.69108 10.4445 5.11883C13.3193 4.54659 16.198 6.08477 17.32 8.79267C18.4421 11.5006 17.495 14.624 15.058 16.2528C12.621 17.8815 9.37287 17.562 7.3 15.4895C6.14763 14.3376 5.50014 12.775 5.5 11.1455Z"
                              stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M15.989 15.4905L19.5 19.0015" stroke="currentColor" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </form>

        @if ($queryTerm)
            <div class="w-full max-w-4xl mt-6">

                {{-- 1. USERS RESULTS SECTION --}}
                @if ($users->isNotEmpty())
                    {{-- <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2 border-gray-200">{{ __('messages.search_results.users') }}</h2>--}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        @foreach($users as $user)
                            @php
                                $profilePic = $user->profile_picture
                                    ? (Str::startsWith($user->profile_picture, ['http', 'https'])
                                        ? $user->profile_picture
                                        : asset('storage/' . $user->profile_picture))
                                    : asset('images/default-pfp.png');

                                $isVerified = in_array($user->username, ['goat', 'umarov']);
                            @endphp
                            <a href="{{ route('profile.show', ['username' => $user->username]) }}"
                               class="flex items-center p-3 bg-white border-1 border-gray-200 rounded-lg hover:border-blue-500 transition duration-200">

                                <img src="{{ $profilePic }}"
                                     alt="{{ __('messages.profile.alt_profile_picture', ['username' => $user->username]) }}"
                                     class="h-12 w-12 rounded-full object-cover border-1 border-gray-200 cursor-pointer zoomable-image flex-shrink-0"
                                     data-full-src="{{ $profilePic }}">

                                <div class="ml-4 flex-1 min-w-0">
                                    <div class="flex items-center">
                                        <p class="text-md font-semibold text-gray-900 truncate"
                                           title="{{ $user->first_name }}">
                                            {{ $user->first_name }}
                                        </p>

                                        @if($isVerified)
                                            <span class="ml-1 flex-shrink-0"
                                                  title="{{ __('messages.profile.verified_account') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20"
                         fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"/>
                    </svg>
                </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 truncate" title="{{ $user->username }}">
                                        {{ $user->username }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- 2. POSTS RESULTS SECTION --}}
                @if ($posts->isNotEmpty())
                    {{-- <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2 border-gray-200">{{ __('messages.search_results.posts') }}</h2>--}}
                    <div class="space-y-4">
                        @foreach ($posts as $post)
                            @include('partials.post-card', ['post' => $post])
                        @endforeach
                    </div>
                    <div class="pagination mt-8">
                        {{ $posts->appends(['q' => $queryTerm])->links() }}
                    </div>
                @endif

                {{-- 3. "NO RESULTS" MESSAGE --}}
                @if ($users->isEmpty() && $posts->isEmpty())
                    <div class="text-center mt-2 mb-8">
                        <p>{{ __('messages.search_results.no_results_found', ['queryTerm' => e($queryTerm)]) }}</p>
                        <p>{{ __('messages.search_results.try_different_keywords') }}</p>
                    </div>
                @endif

            </div>
        @else
            <p class="mt-2 mb-8 text-center">{{ __('messages.search_results.enter_term_prompt') }}</p>
        @endif
    </div>
@endsection
