<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    <h2>{{ __('messages.auth.verify_email_heading') }}</h2>

    <p>{{ __('messages.mail.greeting', ['name' => $user->first_name]) }}</p>

    <p>{{ __('messages.mail.verify_email.line1') }}</p>

    <a href="{{ $verificationUrl }}" class="button">
        {{ __('messages.mail.verify_email.button_text') }}
    </a>

    <p>{!! __('messages.mail.verify_email.important_note') !!}</p>
    <p>{{ __('messages.mail.verify_email.no_action_required') }}</p>

    <p>{{ __('messages.mail.thanks') }}<br>{{ config('app.name') }}</p>

    <div class="footer">
        <p>{!! __('messages.mail.verify_email.if_copy_paste') !!}</p>
        <p style="word-break: break-all;">{{ $verificationUrl }}</p>
    </div>
</div>
</body>
</html>
