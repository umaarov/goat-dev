@component('mail::message')
    # {{ __('messages.mail.registration_expired.title') }}

    {{ __('messages.mail.greeting', ['name' => $user->first_name]) }}

    {{ __('messages.mail.registration_expired.line1', ['app_name' => config('app.name')]) }}

    {{ __('messages.mail.registration_expired.line2') }}

    @component('mail::button', ['url' => route('register')])
        {{ __('messages.mail.registration_expired.button') }}
    @endcomponent

    {{ __('messages.mail.thank_you') }}<br>
    {{ config('app.name') }}
@endcomponent
