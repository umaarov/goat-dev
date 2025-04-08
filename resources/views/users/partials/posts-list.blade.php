@forelse ($posts as $post)
    @include('partials.post-card', ['post' => $post])
@empty
    {{-- This case might be handled by the JS if the first load returns nothing,
         but kept here for potential direct include scenarios. --}}
    {{-- <p>No posts found in this section.</p> --}}
@endforelse
