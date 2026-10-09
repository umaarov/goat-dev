<x-mail.layout :title="__('mail.milestone.subject', ['count' => $count])" :preheader="__('mail.milestone.preheader', ['question' => $question])" :reason="__('mail.footer.author')" :unsubscribe-url="$unsubscribeUrl" :preferences-url="$preferencesUrl">
    <x-mail.title>{{ __('mail.milestone.title', ['count' => $count]) }}</x-mail.title>
    <x-mail.p>{{ __('mail.milestone.intro', ['name' => $name]) }}</x-mail.p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="dm-soft" style="border-collapse: separate; margin: 8px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px;">
        <tr>
            <td style="padding: 20px;">
                <a href="{{ $url }}" target="_blank" style="text-decoration: none;">
                    <img src="{{ $card }}" width="520" alt="{{ $question }}" style="display: block; width: 100%; max-width: 520px; height: auto; border-radius: 10px; background-color: #e2e8f0;">
                </a>
                <h2 class="dm-heading" style="margin: 16px 0 12px; font-size: 20px; line-height: 28px; font-weight: 700; color: #0f172a;">{{ $question }}</h2>
                @foreach ([[$one, $pctOne, '#1d4ed8'], [$two, $pctTwo, '#dc2626']] as [$label, $pct, $color])
                    <p class="dm-text" style="margin: 0 0 4px; font-size: 15px; line-height: 22px; color: #334155;">{{ $label }} <strong>{{ rtrim(rtrim(number_format($pct, 1), '0'), '.') }}%</strong></p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 12px;">
                        <tr>
                            <td width="{{ max(1, (int) round($pct)) }}%" height="8" bgcolor="{{ $color }}" style="background-color: {{ $color }}; height: 8px; font-size: 0; line-height: 0; border-radius: 4px;">&nbsp;</td>
                            <td class="dm-border" bgcolor="#e2e8f0" style="background-color: #e2e8f0; height: 8px; font-size: 0; line-height: 0; border-radius: 4px;">&nbsp;</td>
                        </tr>
                    </table>
                @endforeach
                <x-mail.button :url="$url" :width="220" :flush="true">{{ __('mail.milestone.cta') }}</x-mail.button>
            </td>
        </tr>
    </table>

    <x-mail.p :muted="true">{{ __('mail.milestone.tip') }}</x-mail.p>
</x-mail.layout>
