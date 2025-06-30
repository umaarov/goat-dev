@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Log;use Illuminate\Support\Str;

    function getEmbeddedImageSource($imagePath) {
        if (!$imagePath) {
            return '';
        }

        try {
            $fullPath = Storage::disk('public')->path($imagePath);

            if (Storage::disk('public')->exists($imagePath)) {
                $fileContents = Storage::disk('public')->get($imagePath);
                $mimeType = Storage::disk('public')->mimeType($imagePath);
                return 'data:' . $mimeType . ';base64,' . base64_encode($fileContents);
            }
        } catch (Exception $e) {
            Log::error('Email Image Embedding Failed: ' . $e->getMessage(), ['path' => $imagePath]);
            return '';
        }

        return '';
    }
@endphp
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Posts on GOAT.uz</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .header img {
            max-width: 120px;
        }

        .content {
            padding: 20px;
        }

        .footer, .bottom-footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            background-color: #f8fafc;
        }

        .footer a {
            color: #4f46e5;
        }

        .bottom-footer {
            border-top: 1px solid #e2e8f0;
        }

        h1, h2, h3 {
            margin-top: 0;
        }

        p {
            line-height: 1.5;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4f46e5;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .post-card {
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .post-caption {
            padding: 20px;
        }

        .post-caption h2 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .post-caption p {
            margin-bottom: 15px;
            color: #334155;
        }

        .sub-text {
            font-size: 12px;
            color: #64748b;
            text-align: center;
            margin-top: 10px;
        }

        .grid-header {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <a href="{{ url('/') }}"><img src="{{ asset('images/main_logo.png') }}" alt="GOAT.uz Logo"></a>
    </div>

    <div class="content">
        {{-- =================== MAIN POST =================== --}}
        <div class="post-card">
            @php
                $mainPostUrl = route('posts.showSlug', ['id' => $mainPost->id, 'slug' => Str::slug($mainPost->question)]);
            @endphp
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 20px 20px 0 20px;">
                        <h2 style="font-size: 18px; margin: 0; text-align: center; font-weight: bold;">Hottest
                            Debate: {{ $mainPost->question }}</h2>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 20px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td width="50%" valign="top" style="padding-right: 5px;">
                                    <a href="{{ $mainPostUrl }}"><img
                                            src="{{ Storage::url($mainPost->option_one_image) }}"
                                            alt="{{ $mainPost->option_one_title }}" width="100%"
                                            style="border-radius: 8px; display: block; border: 1px solid #e2e8f0;"></a>
                                </td>
                                <td width="50%" valign="top" style="padding-left: 5px;">
                                    <a href="{{ $mainPostUrl }}"><img
                                            src="{{ Storage::url($mainPost->option_two_image) }}"
                                            alt="{{ $mainPost->option_two_title }}" width="100%"
                                            style="border-radius: 8px; display: block; border: 1px solid #e2e8f0;"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <div class="post-caption">
                <p style="text-align:center;">{{ $mainPost->option_one_title }} vs {{ $mainPost->option_two_title }}</p>
                <div style="text-align: center;">
                    <a href="{{ $mainPostUrl }}" class="button">See More & Vote</a>
                </div>
                <p class="sub-text">See the results now on GOAT.uz</p>
            </div>
        </div>

        {{-- =================== MORE FOR YOU (GRID POSTS) =================== --}}
        @if($gridPosts->count() > 0)
            <h3 class="grid-header">More For You</h3>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                @foreach($gridPosts->chunk(2) as $chunk)
                    <tr>
                        @foreach($chunk as $post)
                            <td width="50%" valign="top" style="padding: 0 10px 20px 10px;">
                                @php
                                    $postUrl = route('posts.showSlug', ['id' => $post->id, 'slug' => Str::slug($post->question)]);
                                @endphp
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
                                       class="post-card" style="margin-bottom: 0;">
                                    <tr>
                                        <td style="padding: 10px;">
                                            <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                                                   width="100%">
                                                <tr>
                                                    <td width="50%" valign="top" style="padding-right: 4px;">
                                                        <a href="{{ $postUrl }}"><img
                                                                src="{{ Storage::url($post->option_one_image) }}"
                                                                alt="{{ $post->option_one_title }}" width="100%"
                                                                style="border-radius: 4px; display: block;"></a>
                                                    </td>
                                                    <td width="50%" valign="top" style="padding-left: 4px;">
                                                        <a href="{{ $postUrl }}"><img
                                                                src="{{ Storage::url($post->option_two_image) }}"
                                                                alt="{{ $post->option_two_title }}" width="100%"
                                                                style="border-radius: 4px; display: block;"></a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px; text-align: center;">
                                            <p style="font-size: 14px; color: #1e293b; font-weight: 500; margin:0;">
                                                <a href="{{ $postUrl }}" style="text-decoration: none; color: #1e293b;">
                                                    {{ Illuminate\Support\Str::limit($post->question, 50) }}
                                                </a>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        @endforeach
                        @if ($chunk->count() < 2)
                            <td width="50%" valign="top"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="footer">
        <p>Have questions? Either respond to this email or contact us on <a href="mailto:info@goat.uz">info@goat.uz</a>.
        </p>
    </div>

    <div class="bottom-footer">
        <p>GOAT uz, Sergeli, Tashkent, 100022, Uzbekistan, 33-532-2517</p>
        <p><a href="#">Unsubscribe</a> &nbsp;|&nbsp; <a href="#">Manage preferences</a></p>
    </div>
</div>
</body>
</html>
