@if($posts->count() > 0)
    @foreach($posts as $post)
        @include('partials.post-card', ['post' => $post, 'showManagementOptions' => $showManagementOptions ?? false])
    @endforeach
@else
    <p class="text-gray-500 text-center py-8">No posts found.</p>
@endif
