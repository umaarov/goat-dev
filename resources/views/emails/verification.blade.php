@component('mail::message')
    # {{ __('messages.auth.verify_email_heading') }}

    {{ __('messages.mail.greeting', ['name' => $user->first_name]) }}

    {{ __('messages.mail.verify_email.line1') }}

    @component('mail::button', ['url' => $verificationUrl])
        {{ __('messages.mail.verify_email.button_text') }}
    @endcomponent

    {!! __('messages.mail.verify_email.important_note') !!}

    {{ __('messages.mail.verify_email.no_action_required') }}

    {{ __('messages.mail.thanks') }}<br>
    {{ config('app.name') }}

    <small>{!! __('messages.mail.verify_email.if_copy_paste') !!}</small><br>
    <small>{{ $verificationUrl }}</small>
@endcomponent
