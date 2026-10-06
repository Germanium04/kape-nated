<x-staff.shell title="Order">

<div class="pos">
    <section class="menu-pane">
        <div class="filters">
            <label>Type: <select id="type"></select></label>
            <label>Search: <input id="search" type="search" autocomplete="off"></label>
        </div>
        <div class="grid" id="grid"></div>
    </section>

    <aside class="ticket">
        <div class="meta">
            <span>Order No. <b id="orderNo">{{ $nextNo }}</b></span>
            <span>{{ $branch ?? 'No branch' }} · <b>{{ now()->format('M j, Y') }}</b></span>
        </div>
        <h3>Order List</h3>
        <div class="lines" id="lines"></div>

        <div class="seg pay" role="radiogroup" aria-label="Payment method">
            <input type="radio" name="pay" id="payCash" value="cash" checked><label for="payCash">Cash</label>
            <input type="radio" name="pay" id="payGcash" value="gcash"><label for="payGcash">GCash</label>
        </div>
        <div class="total"><span>Total</span><strong id="total">₱0.00</strong></div>
        <div class="msg" id="msg" role="status"></div>
        <button class="btn" id="complete" disabled>Complete Order</button>
    </aside>
</div>

<x-staff.modal id="addonModal" withPhoto>
    <h2 id="mTitle"></h2>
    <div class="sub">Add on:</div>
    <div id="mAddons"></div>
    <div class="seg">
        <input type="radio" name="temp" id="tCold" value="cold" checked><label for="tCold">Cold</label>
        <input type="radio" name="temp" id="tHot" value="hot"><label for="tHot">Hot</label>
    </div>
    <button type="button" class="btn" id="mAdd">Add</button>
</x-staff.modal>

@push('scripts')
    <script>
        window.KAPE_ORDER = {
            menu:     @json($menu),
            addons:   @json($addons),
            storeUrl: @json(route('staff.orders.store')),
        };
        initStaffOrder();
    </script>
@endpush

</x-staff.shell>