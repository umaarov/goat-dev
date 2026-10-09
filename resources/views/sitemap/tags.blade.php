<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    @include('sitemap._url', [
        'loc' => route('tags.index'),
        'lastmod' => now()->tz('Asia/Tashkent')->toAtomString(),
        'changefreq' => 'weekly', 'priority' => '0.6',
    ])
    @foreach ($tags as $tag)
        @include('sitemap._url', [
            'loc' => route('tags.show', ['slug' => $tag->slug]),
            'lastmod' => \Illuminate\Support\Carbon::parse($tag->posts_max_updated_at)->tz('Asia/Tashkent')->toAtomString(),
            'changefreq' => 'weekly', 'priority' => '0.6',
        ])
    @endforeach
</urlset>
