<x-staff.shell title="Order">

<style>
    /* Order-screen extras. Kept in this page so they always load with it. */
    .card, .card .pic { position: relative; }
    .price-tag {
        position: absolute; top: 8px; right: 8px;
        padding: 3px 10px; border-radius: 999px;
        background: var(--bar); color: #fdf6ee;
        font-family: 'Karla', system-ui, sans-serif; font-weight: 600; font-size: 12.5px; line-height: 1.3;
        font-variant-numeric: tabular-nums;
    }
    .m-type { margin: 2px 0 0; text-align: center; font-size: 13px; color: var(--ink-soft); }
</style>

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

    <!-- Cash Payment Inputs (Toggled when Cash is selected) -->
    <div id="cashCalcArea" style="margin: 10px 0; padding: 8px; background: rgba(0,0,0,0.03); border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label for="cashTendered" style="font-size: 13px; font-weight: 600;">Cash Received:</label>
            <input type="text" id="cashTendered" inputmode="decimal" placeholder="0.00" style="width: 100px; text-align: right; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; font-weight: 600;">
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; color: var(--ink);">
            <span>Change:</span>
            <span id="cashChange">₱0.00</span>
        </div>
    </div>

    <div class="total"><span>Total</span><strong id="total">₱0.00</strong></div>
    <div class="msg" id="msg" role="status"></div>
    <button class="btn" id="complete" disabled>Complete Order</button>
</aside>
</div>

<x-staff.modal id="addonModal" withPhoto>
    <h2 id="mTitle"></h2>
    <p class="m-type" id="mType"></p>

    {{-- Each block below is filled in (or hidden) by Staff.js depending on the drink. --}}

    {{-- Size: Grande / Venti, or Small cone / Giant swirl. Same two-pill toggle as Cold / Hot. --}}
    <div id="mSizeWrapper" hidden>
        <div class="sub">Size</div>
        <div class="seg" id="mSizes"></div>
    </div>

    {{-- Hot / Cold: only drinks that come both ways --}}
    <div id="mTempWrapper" hidden>
        <div class="sub">Temperature</div>
        <div class="seg">
            <input type="radio" name="temp" id="tCold" value="cold" checked>
            <label for="tCold">Cold</label>
            <input type="radio" name="temp" id="tHot" value="hot">
            <label for="tHot">Hot</label>
        </div>
    </div>

    {{-- Add-ons: only the ones assigned to this drink's type (coffee: shot / syrup / ice cream, soda: boba / jelly) --}}
    <div id="mAddonsWrapper" hidden>
        <div class="sub" id="mAddonsHead">Add-ons</div>
        <div id="mAddons"></div>
    </div>

    <button type="button" class="btn" id="mAdd">Add to Order</button>
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