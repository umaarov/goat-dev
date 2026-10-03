<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    @include('sitemap._url', ['loc' => url('/'), 'lastmod' => $homeLastmod, 'changefreq' => 'daily', 'priority' => '1.0'])
    @foreach ($pages as $page)
        @include('sitemap._url', ['loc' => url($page['path']), 'lastmod' => '2024-01-01T00:00:00+05:00', 'changefreq' => $page['changefreq'], 'priority' => $page['priority']])
    @endforeach
</urlset>
