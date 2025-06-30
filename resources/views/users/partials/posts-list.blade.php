@foreach($posts as $post)
    @include('partials.post-card', [
        'post' => $post,
        'isFirst' => $loop->first && $posts->currentPage() === 1,
    ])
@endforeach
