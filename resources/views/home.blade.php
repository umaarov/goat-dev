@extends('layouts.app')

@section('title', 'Home - Posts')

@section('content')
    <div class="">
        @forelse ($posts as $post)
            @include('partials.post-card', ['post' => $post])
        @empty
            <p>No posts found.</p>
        @endforelse

        <div class="pagination">
            {{ $posts->links() }}
        </div>
    </div>
@endsection
