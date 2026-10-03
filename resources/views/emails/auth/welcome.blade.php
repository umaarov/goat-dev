<x-mail.layout :title="__('mail.welcome.subject', ['name' => $name])" :preheader="__('mail.welcome.preheader')" :reason="__('mail.footer.account')" :preferences-url="$preferencesUrl">
    <x-mail.title>{{ __('mail.welcome.title', ['name' => $name]) }}</x-mail.title>
    <x-mail.p>{{ __('mail.welcome.intro') }}</x-mail.p>

    @foreach ([1 => $trendingUrl, 2 => $askUrl, 3 => $profileUrl] as $n => $link)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="dm-soft" style="border-collapse: separate; margin: 0 0 12px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
            <tr>
                <td width="52" valign="top" style="padding: 18px 0 18px 18px;">
                    <div style="width: 32px; height: 32px; line-height: 32px; text-align: center; border-radius: 16px; background-color: #1d4ed8; color: #ffffff; font-size: 15px; font-weight: 700;">{{ $n }}</div>
                </td>
                <td valign="top" style="padding: 18px 18px 18px 8px;">
                    <p class="dm-heading" style="margin: 0 0 2px; font-size: 16px; line-height: 24px; font-weight: 700; color: #0f172a;">{{ __("mail.welcome.step{$n}_title") }}</p>
                    <p class="dm-text" style="margin: 0 0 8px; font-size: 15px; line-height: 23px; color: #475569;">{{ __("mail.welcome.step{$n}_text") }}</p>
                    <a href="{{ $link }}" target="_blank" class="dm-link" style="font-size: 15px; font-weight: 600; color: #1d4ed8; text-decoration: none;">{{ __("mail.welcome.step{$n}_button") }} &rarr;</a>
                </td>
            </tr>
        </table>
    @endforeach

    <x-mail.p :muted="true">{{ __('mail.welcome.outro') }}</x-mail.p>
</x-mail.layout>
