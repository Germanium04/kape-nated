@props([
    'title' => null,
])

<div class="page">
    @if($title)
        <h1>{{ $title }}</h1>
    @endif

    {{ $slot }}
</div>