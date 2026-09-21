@props([
    'id'    => 'modal',
    'title' => '',
    'size'  => 'md',   // sm | md | lg
])

<div class="modal" id="{{ $id }}" hidden>
    <div class="modal-scrim" data-close-modal="{{ $id }}"></div>

    <div class="modal-box modal-box--{{ $size }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="modal-head">
            <h2>{{ $title }}</h2>
            <button type="button" class="modal-x" data-close-modal="{{ $id }}" aria-label="Close">&times;</button>
        </div>

        <div class="modal-body">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="modal-foot">{{ $footer }}</div>
        @endisset
    </div>
</div>