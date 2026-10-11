@props([
    'tone' => 'ok',   // ok | low | out
])

<span {{ $attributes->merge(['class' => 'badge' . ($tone !== 'ok' ? ' '.$tone : '')]) }}>{{ $slot }}</span>