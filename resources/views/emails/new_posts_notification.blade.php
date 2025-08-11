<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }

        .hover-text-indigo-500:hover {
            color: #6366f1 !important;
        }

        @media (prefers-color-scheme: dark) {
            body, .body, .email-container {
                background-color: #111827 !important;
                color: #f3f4f6 !important;
            }

            .email-content {
                background-color: #1f2937 !important;
            }

            .email-header, .email-footer, .card, .card-body {
                background-color: #374151 !important;
                border-color: #4b5563 !important;
            }

            h1, h2, h3, .dark-text-white {
                color: #ffffff !important;
            }

            p, .dark-text-gray {
                color: #d1d5db !important;
            }

            .button {
                background-color: #818cf8 !important;
            }

            .button a {
                color: #111827 !important;
            }

            .border-gray {
                border-color: #4b5563 !important;
            }

            .text-gray {
                color: #9ca3af !important;
            }

            .logo-dark {
                display: block !important;
            }

            .logo-light {
                display: none !important;
            }
        }
    </style>
</head>

<body class="body"
      style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;word-break: break-word;margin: 0;width: 100%;background-color: #f8fafc;padding: 0;">
<div role="article" aria-roledescription="email" aria-label="New Posts on GOAT.uz" lang="en">
    <table class="email-container" role="presentation" align="center" width="100%" cellpadding="0" cellspacing="0"
           style="width: 100%; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;">
        <tr>
            <td align="center">
                <table class="email-content" role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width: 600px; background-color: #ffffff;">

                    {{-- HEADER --}}
                    <tr>
                        <td class="email-header" style="background-color: #f8fafc; padding: 24px; text-align: center;">
                            <a href="{{ url('/') }}">
                                <img src="{{ asset('images/main_logo.png') }}" width="120" alt="GOAT.uz Logo"
                                     class="logo-light"
                                     style="border: 0; max-width: 100%; vertical-align: middle; line-height: 100%;">
                                <img src="{{ asset('images/main_logo_white.png') }}" width="120" alt="GOAT.uz Logo"
                                     class="logo-dark"
                                     style="display: none; border: 0; max-width: 100%; vertical-align: middle; line-height: 100%;">
                            </a>
                        </td>
                    </tr>

                    {{-- BODY --}}
                    <tr>
                        <td style="padding: 32px 24px;">
                            <h1 class="dark-text-white"
                                style="margin: 0 0 16px; font-size: 24px; font-weight: 700; color: #111827;">
                                Hi, {{ $user->first_name }}!</h1>
                            <p class="dark-text-gray"
                               style="margin: 0 0 24px; font-size: 16px; line-height: 24px; color: #4b5563;">New debates
                                are live and the community is already weighing in. See the hottest topics and cast your
                                vote.</p>

                            {{-- MAIN POST CARD --}}
                            <table class="card" role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="border-radius: 8px; border: 1px solid #e5e7eb; background-color: #f8fafc;">
                                <tr>
                                    <td class="card-body" style="padding: 24px;">
                                        <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6366f1;">
                                            Hottest Debate</p>
                                        <h2 class="dark-text-white"
                                            style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #111827;">{{ $mainPostData['question'] }}</h2>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="50%" valign="top" style="width: 50%; padding-right: 8px;">
                                                    <a href="{{ $mainPostData['url'] }}"><img
                                                            src="{{ $mainPostData['option_one_image'] }}"
                                                            alt="{{ $mainPostData['option_one_title'] }}"
                                                            style="width: 100%; max-width: 100%; border-radius: 6px; display: block; border: 0; vertical-align: middle; line-height: 100%;"></a>
                                                </td>
                                                <td width="50%" valign="top" style="width: 50%; padding-left: 8px;">
                                                    <a href="{{ $mainPostData['url'] }}"><img
                                                            src="{{ $mainPostData['option_two_image'] }}"
                                                            alt="{{ $mainPostData['option_two_title'] }}"
                                                            style="width: 100%; max-width: 100%; border-radius: 6px; display: block; border: 0; vertical-align: middle; line-height: 100%;"></a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p class="dark-text-gray"
                                           style="margin: 16px 0; text-align: center; font-size: 16px; font-weight: 500; color: #4b5563;">{{ $mainPostData['option_one_title'] }}
                                            vs {{ $mainPostData['option_two_title'] }}</p>
                                        @if($mainPostData['total_votes'] > 5)
                                            <p class="text-gray"
                                               style="text-align: center; font-size: 14px; margin: -8px 0 16px;">
                                                🔥 {{ $mainPostData['total_votes'] }} people have already voted!</p>
                                        @endif
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center">
                                                    <table class="button" role="presentation" border="0" cellpadding="0"
                                                           cellspacing="0"
                                                           style="border-radius: 6px; background-color: #4f46e5;">
                                                        <tr>
                                                            <td align="center"
                                                                style="font-size: 16px; font-weight: 600; padding: 14px 24px;">
                                                                <a href="{{ $mainPostData['url'] }}"
                                                                   style="text-decoration: none; color: #ffffff;">See
                                                                    More &amp; Vote</a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            @if(!empty($gridPostsData))
                                {{-- MORE FOR YOU HEADER --}}
                                <h3 class="dark-text-white"
                                    style="margin: 32px 0 16px; padding-top: 24px; font-size: 18px; font-weight: 700; border-top: 1px solid #e5e7eb; color: #111827;">
                                    More For You</h3>

                                {{-- DYNAMIC LAYOUT SWITCH --}}
                                @switch($layoutVariation)

                                    @case('vertical_list')
                                        {{-- Vertical List Layout --}}
                                        @foreach($gridPostsData as $post)
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                                   style="margin-bottom: 16px;">
                                                <tr>
                                                    <td width="80" style="padding-right: 16px;">
                                                        <a href="{{ $post['url'] }}"><img
                                                                src="{{ $post['option_one_image'] }}" alt="" width="80"
                                                                style="border-radius: 6px; aspect-ratio: 1/1; object-fit: cover; border: 0; max-width: 100%; vertical-align: middle; line-height: 100%;"></a>
                                                    </td>
                                                    <td>
                                                        <a href="{{ $post['url'] }}" class="dark-text-white"
                                                           style="text-decoration: none; font-size: 16px; font-weight: 600; color: #1f2937;">{{ Str::limit($post['question'], 60) }}</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endforeach
                                        @break

                                    @case('grid_2x2')
                                    @default
                                        {{-- 2x2 Grid Layout --}}
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            @foreach(array_chunk($gridPostsData, 2) as $chunk)
                                                <tr>
                                                    @foreach($chunk as $post)
                                                        <td width="50%" valign="top"
                                                            style="width: 50%; padding: 0 8px 16px;">
                                                            <a href="{{ $post['url'] }}"><img
                                                                    src="{{ $post['option_one_image'] }}" alt=""
                                                                    style="width: 100%; max-width: 100%; border-radius: 6px; margin-bottom: 8px; aspect-ratio: 1/1; object-fit: cover; border: 0; vertical-align: middle; line-height: 100%;"></a>
                                                            <a href="{{ $post['url'] }}" class="dark-text-white"
                                                               style="text-decoration: none; font-size: 14px; font-weight: 600; color: #1f2937;">{{ Str::limit($post['question'], 50) }}</a>
                                                        </td>
                                                    @endforeach
                                                    @if (count($chunk) < 2)
                                                        <td width="50%"></td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </table>
                                        @break

                                @endswitch
                            @endif
                        </td>
                    </tr>

                    {{-- FOOTER --}}
                    <tr>
                        <td class="email-footer" style="padding: 24px; text-align: center; background-color: #f8fafc;">
                            <p class="text-gray" style="margin: 0 0 12px; font-size: 12px; line-height: 16px; color: #6b7280;">
                                You received this email because you opted in for updates.
                            </p>
                            <p style="margin: 0; font-size: 12px;">
                                <a href="{{ route('notifications.unsubscribe', ['token' => $unsubscribeToken]) }}" class="hover-text-indigo-500"
                                   style="text-decoration: none; color: #6366f1;">Unsubscribe</a> &bull;
                                <a href="{{ route('profile.edit') }}" class="hover-text-indigo-500"
                                   style="text-decoration: none; color: #6366f1;">Email Preferences</a>
                            </p>
                            <p class="text-gray" style="margin: 12px 0 0; font-size: 12px; line-height: 16px; color: #6b7280;">
                                GOAT.uz, Sergeli, Tashkent, 100022, Uzbekistan
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
