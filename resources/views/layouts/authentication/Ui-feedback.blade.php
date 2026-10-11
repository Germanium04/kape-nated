{{-- Shared success/error toasts + confirmation dialog.
     Include once per page, just before </body>:  @include('partials.ui-feedback')
     Requires Authenticate.css and Authenticate.js on the page. --}}
@php
    $toastError = session('error')
        ?? ($errors->any()
            ? ($errors->count() === 1 ? $errors->first() : 'Please fix the highlighted fields.')
            : null);
@endphp

<div class="ui-toasts" aria-live="polite">
    @if (session('success'))
        <div class="ui-toast ui-toast--good" role="status" data-autoclose="5000">
            <span class="ui-toast__msg">{{ session('success') }}</span>
            <button type="button" class="ui-toast__close" aria-label="Dismiss">&times;</button>
        </div>
    @endif

    @if ($toastError)
        <div class="ui-toast ui-toast--bad" role="alert" data-autoclose="8000">
            <span class="ui-toast__msg">{{ $toastError }}</span>
            <button type="button" class="ui-toast__close" aria-label="Dismiss">&times;</button>
        </div>
    @endif
</div>

<dialog id="ui-confirm" class="ui-dialog" aria-labelledby="ui-confirm-title" aria-describedby="ui-confirm-msg">
    <form method="dialog">
        <h3 id="ui-confirm-title">Are you sure?</h3>
        <p id="ui-confirm-msg"></p>
        <div class="ui-dialog__actions">
            <button type="submit" value="cancel" id="ui-confirm-cancel" class="btn btn--ghost">Cancel</button>
            <button type="submit" value="ok" id="ui-confirm-ok" class="btn btn--primary">Confirm</button>
        </div>
    </form>
</dialog>