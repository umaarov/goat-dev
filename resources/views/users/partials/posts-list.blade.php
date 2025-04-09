@forelse ($posts as $post)
    @include('partials.post-card', ['post' => $post])
@empty
    {{-- <p>No posts found in this section.</p> --}}
@endforelse
