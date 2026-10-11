@props([
    'id'        => 'modal',
    'withPhoto' => false,
])

<div class="backdrop" id="{{ $id }}">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <button type="button" class="close" data-close-modal="{{ $id }}" aria-label="Close">&times;</button>

        @if($withPhoto)
            <div class="photo"></div>
        @endif

        <div class="body">
            {{ $slot }}
        </div>
    </div>
</div>