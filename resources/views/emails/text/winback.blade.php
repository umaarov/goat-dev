{!! __('mail.winback.title', ['name' => $name]) !!}

{!! __('mail.winback.intro') !!}

@foreach($items as $item)
- {!! $item['question'] !!} ({!! $item['one'] !!} vs {!! $item['two'] !!})
  {!! $item['url'] !!}
@endforeach

{!! __('mail.winback.cta') !!}: {!! $homeUrl !!}

{!! __('mail.winback.outro') !!}

{!! __('mail.signature') !!}
{!! __('mail.footer.unsubscribe') !!}: {!! $unsubscribeUrl !!}

{!! __("mail.footer.winback") !!}
