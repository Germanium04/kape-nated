@props([
    'tone' => 'ok',   // ok | low | out | info | muted
])

<span {{ $attributes->merge(['class' => 'badge badge--'.$tone]) }}>{{ $slot }}</span>