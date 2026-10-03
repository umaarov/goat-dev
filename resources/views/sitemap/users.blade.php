<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    @foreach ($users as $user)
        @include('sitemap._url', [
            'loc' => route('profile.show', ['username' => $user->username]),
            'lastmod' => $user->updated_at->tz('Asia/Tashkent')->toAtomString(),
            'changefreq' => 'weekly', 'priority' => '0.6',
        ])
    @endforeach
</urlset>
