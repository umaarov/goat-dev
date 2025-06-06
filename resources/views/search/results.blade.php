@extends('layouts.app')

@section('title', $queryTerm ? __('messages.search_results.title_with_query', ['queryTerm' => e($queryTerm)]) : __('messages.search_results.title_default'))

@section('content')
    <div class="flex flex-col items-center justify-center">
        <form action="{{ route('search') }}" method="GET" class="w-full max-w-md mx-auto">
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
                    <svg width="24" height="24" viewBox="0 -0.5 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                              d="M5.5 11.1455C5.49956 8.21437 7.56975 5.69108 10.4445 5.11883C13.3193 4.54659 16.198 6.08477 17.32 8.79267C18.4421 11.5006 17.495 14.624 15.058 16.2528C12.621 17.8815 9.37287 17.562 7.3 15.4895C6.14763 14.3376 5.50014 12.775 5.5 11.1455Z"
                              stroke="#6a7282" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M15.989 15.4905L19.5 19.0015" stroke="#6a7282" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </form>


        @if ($queryTerm)
            {{--            <h3>Results for "{{ e($queryTerm) }}"</h3>--}}

            <div class="mt-2">
                @forelse ($posts as $post)
                    @include('partials.post-card', ['post' => $post])
                @empty
                    <p>{{ __('messages.search_results.no_posts_found') }}</p>
                @endforelse

                <div class="pagination">
                    {{ $posts->appends(['q' => $queryTerm])->links() }}
                </div>
            </div>
        @else
            <p class="mt-2 mb-8">{{ __('messages.search_results.enter_term_prompt') }}</p>
        @endif
    </div>
@endsection
