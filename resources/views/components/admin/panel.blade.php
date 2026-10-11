@props([
    'title' => null,
    'note'  => null,
    'tone'  => 'plain',   // plain | warning
])

<section {{ $attributes->merge(['class' => 'panel panel--'.$tone]) }}>
    @if($title)
        <div class="panel-head">
            <h2>{{ $title }}</h2>
            @if($note)<span class="panel-note">{{ $note }}</span>@endif
            @isset($tools)<div class="panel-tools">{{ $tools }}</div>@endisset
        </div>
    @endif

    <div class="panel-body">
        {{ $slot }}
    </div>
</section>