@props(['url'])
<div class="dm-border" style="margin-top: 8px; padding-top: 18px; border-top: 1px solid #e2e8f0;">
    <p class="dm-muted" style="margin: 0 0 6px; font-size: 13px; line-height: 20px; color: #64748b;">{{ __('mail.link_fallback') }}</p>
    <p style="margin: 0; font-size: 13px; line-height: 20px; word-break: break-all;"><a href="{{ $url }}" class="dm-link" style="color: #1d4ed8;">{{ $url }}</a></p>
</div>
