{!! __('mail.milestone.title', ['count' => $count]) !!}

{!! __('mail.milestone.intro', ['name' => $name]) !!}

{!! $question !!}
{!! $one !!}: {{ rtrim(rtrim(number_format($pctOne, 1), '0'), '.') }}%
{!! $two !!}: {{ rtrim(rtrim(number_format($pctTwo, 1), '0'), '.') }}%

{!! __('mail.milestone.cta') !!}: {!! $url !!}

{!! __('mail.milestone.tip') !!}

{!! __('mail.signature') !!}
{!! __('mail.footer.unsubscribe') !!}: {!! $unsubscribeUrl !!}

{!! __("mail.footer.author") !!}
