@props([
    'title',
    'preheader' => '',
    'reason' => null,
    'unsubscribeUrl' => null,
    'preferencesUrl' => null,
])
@php
    $font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        body { margin: 0; padding: 0; width: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; -ms-interpolation-mode: bicubic; }
        a { color: #1d4ed8; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .stack { display: block !important; width: 100% !important; padding: 0 0 12px 0 !important; }
            .btn-cell a { display: block !important; }
            .btn-table { width: 100% !important; }
            .title { font-size: 22px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .dm-bg { background-color: #020617 !important; }
            .dm-card { background-color: #0f172a !important; }
            .dm-soft { background-color: #1e293b !important; border-color: #334155 !important; }
            .dm-border { border-color: #1e293b !important; }
            .dm-heading { color: #f8fafc !important; }
            .dm-text { color: #e2e8f0 !important; }
            .dm-muted { color: #94a3b8 !important; }
            .dm-link { color: #93c5fd !important; }
            .dm-btn { background-color: #3b82f6 !important; }
            .dm-accent { color: #93c5fd !important; }
        }
        /* Outlook.com / Outlook apps */
        [data-ogsc] .dm-bg { background-color: #020617 !important; }
        [data-ogsc] .dm-card { background-color: #0f172a !important; }
        [data-ogsc] .dm-soft { background-color: #1e293b !important; border-color: #334155 !important; }
        [data-ogsc] .dm-heading { color: #f8fafc !important; }
        [data-ogsc] .dm-text { color: #e2e8f0 !important; }
        [data-ogsc] .dm-muted { color: #94a3b8 !important; }
        [data-ogsc] .dm-link { color: #93c5fd !important; }
        [data-ogsc] .dm-btn { background-color: #3b82f6 !important; }
    </style>
</head>
<body class="dm-bg" style="margin: 0; padding: 0; background-color: #f1f5f9;">
{{-- the grey line some inboxes show next to the subject --}}
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #f1f5f9; opacity: 0;">
    {{ $preheader }}{!! str_repeat('&nbsp;&zwnj;', 60) !!}
</div>

<div role="article" aria-roledescription="email" aria-label="{{ $title }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="dm-bg" style="background-color: #f1f5f9;">
    <tr>
        <td align="center" style="padding: 24px 12px;">
            <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" style="width: 600px; max-width: 600px;">
                {{-- header: dark in light and dark mode, so one white logo works everywhere --}}
                <tr>
                    <td align="center" bgcolor="#0f172a" style="background-color: #0f172a; border-radius: 14px 14px 0 0; padding: 26px 24px;">
                        <a href="{{ url('/') }}" target="_blank" style="text-decoration: none;">
                            <img src="{{ asset('images/email/logo-white.png') }}" width="128" height="33" alt="{{ __('mail.brand') }}"
                                 style="display: block; width: 128px; height: auto; font-family: {{ $font }}; font-size: 22px; font-weight: 700; color: #ffffff;">
                        </a>
                    </td>
                </tr>
                {{-- card --}}
                <tr>
                    <td class="dm-card px" bgcolor="#ffffff" style="background-color: #ffffff; padding: 36px 36px 32px; font-family: {{ $font }}; font-size: 16px; line-height: 26px; color: #334155;">
                        {{ $slot }}
                    </td>
                </tr>
                <tr>
                    <td class="dm-card px dm-border" bgcolor="#ffffff" style="background-color: #ffffff; padding: 0 36px 28px; border-radius: 0 0 14px 14px; font-family: {{ $font }};">
                        <div class="dm-border" style="border-top: 1px solid #e2e8f0; padding-top: 20px; font-size: 14px; line-height: 22px; color: #64748b;">
                            <span class="dm-muted" style="color: #64748b;">{{ __('mail.signature') }}</span>
                        </div>
                    </td>
                </tr>
                {{-- footer --}}
                <tr>
                    <td align="center" class="px" style="padding: 24px 20px 8px; font-family: {{ $font }}; font-size: 12px; line-height: 20px; color: #64748b;">
                        @if($reason)
                            <p class="dm-muted" style="margin: 0 0 8px; color: #64748b;">{{ $reason }}</p>
                        @endif
                        <p class="dm-muted" style="margin: 0 0 8px; color: #64748b;">
                            @if($preferencesUrl)
                                <a href="{{ $preferencesUrl }}" class="dm-link" style="color: #475569; text-decoration: underline;">{{ __('mail.footer.preferences') }}</a>
                            @endif
                            @if($preferencesUrl && $unsubscribeUrl) &nbsp;&middot;&nbsp; @endif
                            @if($unsubscribeUrl)
                                <a href="{{ $unsubscribeUrl }}" class="dm-link" style="color: #475569; text-decoration: underline;">{{ __('mail.footer.unsubscribe') }}</a>
                            @endif
                        </p>
                        <p class="dm-muted" style="margin: 0 0 8px; color: #64748b;">{{ __('mail.footer.help') }}</p>
                        <p class="dm-muted" style="margin: 0; color: #94a3b8;">{{ config('mail.footer_address') }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</div>
</body>
</html>
