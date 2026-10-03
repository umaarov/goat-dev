<x-mail.layout :title="__('mail.verify.title')" :preheader="__('mail.verify.preheader', ['time' => $time])" :reason="__('mail.footer.account')">
    <x-mail.title>{{ __('mail.verify.title') }}</x-mail.title>
    <x-mail.p>{{ __('mail.greeting', ['name' => $name]) }}</x-mail.p>
    <x-mail.p>{{ __('mail.verify.intro') }}</x-mail.p>

    <x-mail.button :url="$url">{{ __('mail.verify.button') }}</x-mail.button>

    <x-mail.p :muted="true">{{ __('mail.verify.expiry', ['time' => $time]) }}</x-mail.p>
    <x-mail.p :muted="true">{{ __('mail.verify.ignore') }}</x-mail.p>
    <x-mail.link-fallback :url="$url"/>
</x-mail.layout>
