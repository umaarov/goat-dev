{!! __('mail.welcome.title', ['name' => $name]) !!}

{!! __('mail.welcome.intro') !!}

1. {!! __('mail.welcome.step1_title') !!}: {!! __('mail.welcome.step1_text') !!}
   {!! $trendingUrl !!}
2. {!! __('mail.welcome.step2_title') !!}: {!! __('mail.welcome.step2_text') !!}
   {!! $askUrl !!}
3. {!! __('mail.welcome.step3_title') !!}: {!! __('mail.welcome.step3_text') !!}
   {!! $profileUrl !!}

{!! __('mail.welcome.outro') !!}

{!! __('mail.signature') !!}
{!! __('mail.footer.preferences') !!}: {!! $preferencesUrl !!}
