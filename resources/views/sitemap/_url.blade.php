{{-- one page, one <url> per language; every entry lists all variants (hreflang rule for sitemaps) --}}
@php $variants = \App\Support\SeoUrls::variants($loc); @endphp
@foreach ($variants as $code => $url)
    <url>
        <loc>{{ $url }}</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>{{ $changefreq }}</changefreq>
        <priority>{{ $priority }}</priority>
        @foreach ($variants as $altCode => $altUrl)
            <xhtml:link rel="alternate" hreflang="{{ \App\Support\SeoUrls::hreflang($altCode) }}" href="{{ $altUrl }}"/>
        @endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $loc }}"/>
        @foreach (($images ?? []) as $image)
            <image:image>
                <image:loc>{{ $image['loc'] }}</image:loc>
                <image:title>{{ $image['title'] }}</image:title>
                <image:caption>{{ $image['caption'] }}</image:caption>
            </image:image>
        @endforeach
    </url>
@endforeach
