@component('mail::message')
    # Verify Your Email Address

    Hi {{ $user->first_name }},

    Thank you for creating an account. Please verify your email address by clicking the button below.

    @component('mail::button', ['url' => $verificationUrl])
        Verify Email Address
    @endcomponent

    **Important:** This verification link will expire in 1 hour. If you don't verify your email within this timeframe, your registration will be cancelled and you'll need to register again.

    If you did not create an account, no further action is required.

    Thanks,<br>
    {{ config('app.name') }}

    <small>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</small><br>
    <small>{{ $verificationUrl }}</small>
@endcomponent
