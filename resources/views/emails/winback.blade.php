<x-mail.layout :title="__('mail.winback.subject')" :preheader="__('mail.winback.preheader')" :reason="__('mail.footer.winback')" :unsubscribe-url="$unsubscribeUrl" :preferences-url="$preferencesUrl">
    <x-mail.title>{{ __('mail.winback.title', ['name' => $name]) }}</x-mail.title>
    <x-mail.p>{{ __('mail.winback.intro') }}</x-mail.p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @foreach($items as $item)
            <tr>
                <td class="dm-border" style="padding: 14px 0; border-bottom: 1px solid #e2e8f0;">
                    <a href="{{ $item['url'] }}" target="_blank" class="dm-heading" style="display: block; font-size: 16px; line-height: 24px; font-weight: 600; color: #0f172a; text-decoration: none;">{{ $item['question'] }}</a>
                    <span class="dm-muted" style="font-size: 14px; line-height: 22px; color: #64748b;">{{ $item['one'] }} vs {{ $item['two'] }}@if($item['votes'] > 0) &nbsp;&middot;&nbsp; {{ __('mail.digest.votes', ['count' => number_format($item['votes'])]) }}@endif</span>
                </td>
            </tr>
        @endforeach
    </table>

    <x-mail.button :url="$homeUrl" :width="220">{{ __('mail.winback.cta') }}</x-mail.button>
    <x-mail.p :muted="true">{{ __('mail.winback.outro') }}</x-mail.p>
</x-mail.layout>
