<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <url>
        <loc>https://goat.uz</loc>
        <lastmod>{{ now()->startOfDay()->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>https://goat.uz/about</loc>
        <lastmod>2024-01-01T00:00:00+05:00</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc>https://goat.uz/terms</loc>
        <lastmod>2024-01-01T00:00:00+05:00</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>https://goat.uz/sponsorship</loc>
        <lastmod>2024-01-01T00:00:00+05:00</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc>https://goat.uz/ads</loc>
        <lastmod>2024-01-01T00:00:00+05:00</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>

    @foreach ($posts as $post)
        <url>
            <loc>{{ url('p/' . $post->id . '/' . Illuminate\Support\Str::slug($post->question)) }}</loc>
            <lastmod>{{ $post->updated_at->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
            @if ($post->option_one_image)
                <image:image>
                    <image:loc>{{ asset('storage/' . $post->option_one_image) }}</image:loc>
                    <image:title>{{ htmlspecialchars($post->option_one_title) }}</image:title>
                    <image:caption>{{ htmlspecialchars($post->question) }}</image:caption>
                </image:image>
            @endif
            @if ($post->option_two_image)
                <image:image>
                    <image:loc>{{ asset('storage/' . $post->option_two_image) }}</image:loc>
                    <image:title>{{ htmlspecialchars($post->option_two_title) }}</image:title>
                    <image:caption>{{ htmlspecialchars($post->question) }}</image:caption>
                </image:image>
            @endif
        </url>
    @endforeach

    @foreach ($users as $user)
        <url>
            <loc>{{ route('profile.show', ['username' => $user->username]) }}</loc>
            <lastmod>{{ $user->updated_at->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach

</urlset>
