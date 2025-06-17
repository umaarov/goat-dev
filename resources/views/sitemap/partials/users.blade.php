@foreach ($users as $user)
    <url>
        <loc>{{ route('profile.show', ['username' => $user->username]) }}</loc>
        <lastmod>{{ $user->updated_at->tz('Asia/Tashkent')->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
