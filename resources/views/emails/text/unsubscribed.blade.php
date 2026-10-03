{!! __('mail.unsubscribed.title') !!}

{!! __('mail.greeting', ['name' => $name]) !!}

{!! __('mail.unsubscribed.intro') !!}
{!! __('mail.unsubscribed.details', ['when' => $when, 'ip' => $ip]) !!}

{!! __('mail.unsubscribed.changed_mind') !!}
{!! __('mail.unsubscribed.button') !!}: {!! $resubscribeUrl !!}

{!! __('mail.signature') !!}
