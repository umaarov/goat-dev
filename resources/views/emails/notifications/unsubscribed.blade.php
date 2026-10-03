<x-mail.layout :title="__('mail.unsubscribed.title')" :preheader="__('mail.unsubscribed.preheader')" :reason="__('mail.footer.account')">
    <x-mail.title>{{ __('mail.unsubscribed.title') }}</x-mail.title>
    <x-mail.p>{{ __('mail.greeting', ['name' => $name]) }}</x-mail.p>
    <x-mail.p>{{ __('mail.unsubscribed.intro') }}</x-mail.p>
    <x-mail.p :muted="true">{{ __('mail.unsubscribed.details', ['when' => $when, 'ip' => $ip]) }}</x-mail.p>
    <x-mail.p>{{ __('mail.unsubscribed.changed_mind') }}</x-mail.p>

    <x-mail.button :url="$resubscribeUrl">{{ __('mail.unsubscribed.button') }}</x-mail.button>
</x-mail.layout>
