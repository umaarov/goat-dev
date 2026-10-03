<x-mail.layout :title="__('mail.digest.subject')" :preheader="$main['question']" :reason="__('mail.footer.digest')" :unsubscribe-url="$unsubscribeUrl" :preferences-url="$preferencesUrl">
    <x-mail.title>{{ __('mail.digest.title', ['name' => $name]) }}</x-mail.title>
    <x-mail.p>{{ __('mail.digest.intro') }}</x-mail.p>

    {{-- the hottest debate: its share card shows both options and the live split --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="dm-soft" style="border-collapse: separate; margin: 8px 0 8px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px;">
        <tr>
            <td style="padding: 20px 20px 22px;">
                <p class="dm-accent" style="margin: 0 0 8px; font-size: 12px; line-height: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #1d4ed8;">{{ __('mail.digest.hottest') }}</p>
                <a href="{{ $main['url'] }}" target="_blank" style="text-decoration: none;">
                    <img src="{{ $main['card'] }}" width="520" alt="{{ $main['question'] }}" style="display: block; width: 100%; max-width: 520px; height: auto; border-radius: 10px; background-color: #e2e8f0;">
                </a>
                <h2 class="dm-heading" style="margin: 16px 0 6px; font-size: 20px; line-height: 28px; font-weight: 700; color: #0f172a;">
                    <a href="{{ $main['url'] }}" target="_blank" class="dm-heading" style="color: #0f172a; text-decoration: none;">{{ $main['question'] }}</a>
                </h2>
                <p class="dm-muted" style="margin: 0 0 4px; font-size: 15px; line-height: 22px; color: #64748b;">{{ $main['one'] }} <strong>vs</strong> {{ $main['two'] }}</p>
                @if($main['votes'] > 0)
                    <p class="dm-muted" style="margin: 0; font-size: 14px; line-height: 22px; color: #64748b;">🔥 {{ __('mail.digest.votes', ['count' => number_format($main['votes'])]) }}</p>
                @endif
                <x-mail.button :url="$main['url']" :width="220" :flush="true">{{ __('mail.digest.cta') }}</x-mail.button>
            </td>
        </tr>
    </table>

    @if($more !== [])
        <h3 class="dm-heading" style="margin: 28px 0 4px; font-size: 18px; line-height: 26px; font-weight: 700; color: #0f172a;">{{ __('mail.digest.more') }}</h3>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            @foreach($more as $item)
                <tr>
                    <td class="dm-border" style="padding: 14px 0; border-bottom: 1px solid #e2e8f0;">
                        <a href="{{ $item['url'] }}" target="_blank" class="dm-heading" style="display: block; font-size: 16px; line-height: 24px; font-weight: 600; color: #0f172a; text-decoration: none;">{{ $item['question'] }}</a>
                        <span class="dm-muted" style="font-size: 14px; line-height: 22px; color: #64748b;">{{ $item['one'] }} vs {{ $item['two'] }}@if($item['votes'] > 0) &nbsp;&middot;&nbsp; {{ __('mail.digest.votes', ['count' => number_format($item['votes'])]) }}@endif</span>
                        &nbsp;<a href="{{ $item['url'] }}" target="_blank" class="dm-link" style="font-size: 14px; font-weight: 600; color: #1d4ed8; text-decoration: none; white-space: nowrap;">{{ __('mail.digest.view') }} &rarr;</a>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</x-mail.layout>
