<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    @foreach ($posts as $post)
        @php
            $images = [];
            foreach ([1 => $post->option_one_image, 2 => $post->option_two_image] as $n => $path) {
                if ($path) {
                    $images[] = ['loc' => asset('storage/' . $path), 'title' => $n === 1 ? $post->option_one_title : $post->option_two_title, 'caption' => $post->question];
                }
            }
        @endphp
        @include('sitemap._url', [
            'loc' => route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]),
            'lastmod' => $post->updated_at->tz('Asia/Tashkent')->toAtomString(),
            'changefreq' => 'weekly', 'priority' => '0.9', 'images' => $images,
        ])
    @endforeach
</urlset>
