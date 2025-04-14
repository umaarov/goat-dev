@component('mail::message')
    # Registration Expired

    Hi {{ $user->first_name }},

    Your account registration for {{ config('app.name') }} has expired because your email was not verified within the required 1-hour timeframe.

    If you still wish to register, please start the registration process again.

    @component('mail::button', ['url' => route('register')])
        Register Again
    @endcomponent

    Thank you,<br>
    {{ config('app.name') }}
@endcomponent
