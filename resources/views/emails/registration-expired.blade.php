<x-mail.layout :title="__('mail.expired.title')" :preheader="__('mail.expired.preheader')" :reason="__('mail.footer.account')">
    <x-mail.title>{{ __('mail.expired.title') }}</x-mail.title>
    <x-mail.p>{{ __('mail.greeting', ['name' => $name]) }}</x-mail.p>
    <x-mail.p>{{ __('mail.expired.intro', ['time' => $time]) }}</x-mail.p>
    <x-mail.p>{{ __('mail.expired.again') }}</x-mail.p>

    <x-mail.button :url="$url">{{ __('mail.expired.button') }}</x-mail.button>
</x-mail.layout>
