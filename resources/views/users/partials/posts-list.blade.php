@foreach($posts as $post)
    @include('partials.post-card', [
        'post' => $post,
        'showManagementOptions' => $showManagementOptions ?? false,
        'profileOwnerToDisplay' => $profileOwnerToDisplay ?? null,
        'isFirst' => false,
    ])
@endforeach
