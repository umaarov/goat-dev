<x-mail.layout :title="__('mail.reset.title')" :preheader="__('mail.reset.preheader', ['time' => $time])" :reason="__('mail.footer.account')">
    <x-mail.title>{{ __('mail.reset.title') }}</x-mail.title>
    <x-mail.p>{{ __('mail.greeting', ['name' => $name]) }}</x-mail.p>
    <x-mail.p>{{ __('mail.reset.intro') }}</x-mail.p>

    <x-mail.button :url="$url">{{ __('mail.reset.button') }}</x-mail.button>

    <x-mail.p :muted="true">{{ __('mail.reset.expiry', ['time' => $time]) }}</x-mail.p>
    <x-mail.p :muted="true">{{ __('mail.reset.ignore') }}</x-mail.p>
    <x-mail.p :muted="true">{{ __('mail.reset.security') }}</x-mail.p>
    <x-mail.link-fallback :url="$url"/>
</x-mail.layout>
