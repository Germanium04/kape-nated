@props([
    'label'  => '',
    'value'  => '',
    'trend'  => null,      // e.g. "+12% vs yesterday"
    'tone'   => 'neutral', // neutral | up | down | alert
])

<article {{ $attributes->merge(['class' => 'stat stat--'.$tone]) }}>
    <p class="stat-label">{{ $label }}</p>
    <p class="stat-value">{{ $value }}</p>
    @if($trend)
        <p class="stat-trend">{{ $trend }}</p>
    @endif
</article>