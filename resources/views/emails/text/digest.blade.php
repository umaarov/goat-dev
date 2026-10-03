{!! __('mail.digest.title', ['name' => $name]) !!}

{!! __('mail.digest.intro') !!}

{!! strtoupper(__('mail.digest.hottest')) !!}
{!! $main['question'] !!}
{!! $main['one'] !!} vs {!! $main['two'] !!}@if($main['votes'] > 0) ({!! __('mail.digest.votes', ['count' => number_format($main['votes'])]) !!})@endif

{!! __('mail.digest.cta') !!}: {!! $main['url'] !!}
@if($more !== [])

{!! __('mail.digest.more') !!}
@foreach($more as $item)
- {!! $item['question'] !!} ({!! $item['one'] !!} vs {!! $item['two'] !!})
  {!! $item['url'] !!}
@endforeach
@endif

{!! __('mail.signature') !!}

{!! __('mail.footer.digest') !!}
{!! __('mail.footer.unsubscribe') !!}: {!! $unsubscribeUrl !!}
{!! __('mail.footer.preferences') !!}: {!! $preferencesUrl !!}
