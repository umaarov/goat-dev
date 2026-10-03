@props(['muted' => false])
<p class="{{ $muted ? 'dm-muted' : 'dm-text' }}" style="margin: 0 0 16px; {{ $muted ? 'font-size: 14px; line-height: 22px; color: #64748b;' : 'color: #334155;' }}">{{ $slot }}</p>
