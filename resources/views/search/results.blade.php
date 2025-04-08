@extends('layouts.app')

@section('title', $queryTerm ? 'Search Results for "' . e($queryTerm) . '"' : 'Search')

@section('content')
    <h2>Search Posts</h2>

    <form action="{{ route('search') }}" method="GET" class="form-group">
        <input type="search" name="q" value="{{ old('q', $queryTerm) }}"
               placeholder="Search question, options, or username...">
        <button type="submit">Search</button>
    </form>

    @if ($queryTerm)
        <h3>Results for "{{ e($queryTerm) }}"</h3>

        @forelse ($posts as $post)
            @include('partials.post-card', ['post' => $post])
        @empty
            <p>No posts found matching your search query.</p>
        @endforelse

        <div class="pagination">
            {{ $posts->appends(['q' => $queryTerm])->links() }}
        </div>
    @else
        <p>Enter a term above to search for posts.</p>
    @endif

@endsection
