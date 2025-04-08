@extends('layouts.app')

@section('title', 'Home - Posts')

@section('content')
    <h2>Home Feed</h2>

    <div>
        Filter:
        <a href="{{ route('home', ['filter' => 'latest']) }}" class="{{ request('filter', 'latest') == 'latest' ? 'font-bold' : '' }}">Latest</a> |
        <a href="{{ route('home', ['filter' => 'trending']) }}" class="{{ request('filter') == 'trending' ? 'font-bold' : '' }}">Trending</a>
    </div>
    <hr style="margin: 1em 0;">

    @forelse ($posts as $post)
        @include('partials.post-card', ['post' => $post])
    @empty
        <p>No posts found.</p>
    @endforelse

    <div class="pagination">
        {{ $posts->links() }}
    </div>
@endsection
