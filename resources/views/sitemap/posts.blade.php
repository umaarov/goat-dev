<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    @foreach ($posts as $post)
        <url>
            <loc>{{ route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]) }}</loc>
            <lastmod>{{ $post->updated_at->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>

            @if ($post->option_one_image)
                <image:image>
                    <image:loc>{{ asset('storage/' . $post->option_one_image) }}</image:loc>
                    <image:title>{{ $post->option_one_title }}</image:title>
                    <image:caption>{{ $post->question }}</image:caption>
                </image:image>
            @endif

            @if ($post->option_two_image)
                <image:image>
                    <image:loc>{{ asset('storage/' . $post->option_two_image) }}</image:loc>
                    <image:title>{{ $post->option_two_title }}</image:title>
                    <image:caption>{{ $post->question }}</image:caption>
                </image:image>
            @endif
        </url>
    @endforeach
</urlset>
