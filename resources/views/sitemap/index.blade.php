<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>{{ route('sitemap.static') }}</loc>
        <lastmod>{{ now()->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
    </sitemap>
    <sitemap>
        <loc>{{ route('sitemap.posts') }}</loc>
        <lastmod>{{ $latestPost ? $latestPost->updated_at->tz('Asia/Tashkent')->toAtomString() : now()->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
    </sitemap>
    <sitemap>
        <loc>{{ route('sitemap.users') }}</loc>
        <lastmod>{{ $latestUser ? $latestUser->updated_at->tz('Asia/Tashkent')->toAtomString() : now()->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
    </sitemap>
</sitemapindex>
