{{--
    This Blade view serves as the home page, displaying a list of posts.
    It extends the main app layout and includes pagination for posts.
--}}
@extends('layouts.app')

@section('title', __('messages.home.page_title_posts'))
@section('meta_description', __('messages.home.meta_description'))

@section('content')
    <div class="">
        @forelse ($posts as $post)
            @include('partials.post-card', ['post' => $post])
        @empty
            <p>{{ __('messages.app.no_posts_found') }}</p>
        @endforelse

        <div class="pagination">
            {{ $posts->links() }}
        </div>
    </div>
@endsection
