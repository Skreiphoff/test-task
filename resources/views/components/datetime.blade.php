@props(['value'])

@if ($value)
    <time datetime="{{ $value->utc()->toIso8601String() }}">{{ $value->utc()->format('d.m.Y H:i:s') }} UTC</time>
@else
    <span class="muted">—</span>
@endif
