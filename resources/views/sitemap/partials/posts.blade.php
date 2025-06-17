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
