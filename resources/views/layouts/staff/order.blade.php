<x-staff.shell title="Order">

<style>
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
        <div class="filters order-filter-wrap">
            <div class="order-field-type">
                <label for="type" class="order-label-text">Type</label>
                <select id="type" class="field order-input-stretch"></select>
            </div>
            <div class="order-field-type">
                <label for="availabilityFilter" class="order-label-text">Availability</label>
                <select id="availabilityFilter" class="field order-input-stretch">
                    <option value="all">All items</option>
                    <option value="available" selected>Available only</option>
                    <option value="unavailable">Unavailable only</option>
                </select>
            </div>
            <div class="order-field-search">
                <label for="search" class="order-label-text">Search menu</label>
                <input id="search" class="field order-input-stretch" type="search" autocomplete="off" placeholder="🔍 Search drink name...">
            </div>
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

        <!-- Cash Payment Inputs -->
        <div id="cashCalcArea" class="payment-calc-box">
            <div class="payment-calc-row">
                <label for="cashTendered" class="payment-calc-label">Cash Received:</label>
                <input type="text" id="cashTendered" inputmode="decimal" placeholder="0.00" class="payment-tendered-input">
            </div>
            <div class="payment-change-display">
                <span>Change:</span>
                <span id="cashChange">₱0.00</span>
            </div>
        </div>

        <!-- GCash Reference Inputs -->
        <div id="gcashCalcArea" class="payment-calc-box" hidden>
            <div class="payment-calc-row-single">
                <label for="gcashRef" class="payment-calc-label">Ref. No.:</label>
                <input type="text" id="gcashRef" placeholder="Ref. No." class="payment-ref-input">
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

    <div id="mSizeWrapper" hidden>
        <div class="sub">Size</div>
        <div class="seg" id="mSizes"></div>
    </div>

    <div id="mTempWrapper" hidden>
        <div class="sub">Temperature</div>
        <div class="seg">
            <input type="radio" name="temp" id="tCold" value="cold" checked>
            <label for="tCold">Cold</label>
            <input type="radio" name="temp" id="tHot" value="hot">
            <label for="tHot">Hot</label>
        </div>
    </div>

    <div id="mAddonsWrapper" hidden>
        <div class="sub" id="mAddonsHead">Add-ons</div>
        <div id="mAddons"></div>
    </div>

    <button type="button" class="btn" id="mAdd">Add to Order</button>
</x-staff.modal>

<!-- Clean Single-Column Order Receipt Modal -->
<div class="modal" id="receiptModal" hidden>
    <div class="receipt-modal-box">
        <div class="receipt-modal-header">
            <h2>Order Completed!</h2>
            <p id="rcptMeta" class="m-type"></p>
        </div>

        <div class="modal-content-scroll" style="overflow-y: auto; max-height: 55vh;">
            <div id="receiptPrintArea" class="receipt-paper">
                <div class="receipt-paper-head">
                    <h3>Kape-nated</h3>
                    <p class="receipt-branch">{{ $branch ?? 'Main Branch' }}</p>
                    <p id="rcptDate" class="receipt-date"></p>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--line-soft);">
                    <span>Order No. <b>#<span id="rcptOrderNo"></span></b></span>
                    <span>Payment: <b id="rcptPayMethod"></b></span>
                </div>

                <div id="rcptItems" class="receipt-paper-body"></div>

                <div class="receipt-paper-footer">
                    <div class="receipt-row-total">
                        <span>Total Amount</span>
                        <strong id="rcptTotal" style="font-family: 'Baloo 2', sans-serif; font-size: 20px;">₱0.00</strong>
                    </div>
                    <div id="rcptCashRow" class="receipt-row-sub" style="margin-top: 6px;" hidden>
                        <span>Cash Tendered / Change</span>
                        <span id="rcptChange">₱0.00 / ₱0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="receipt-modal-actions" style="margin-top: 14px;">
            <button type="button" class="btn btn--ghost btn--sm" data-close-modal="receiptModal" style="margin: 0; flex: 1;">Done</button>
            <button type="button" class="btn btn--primary btn--sm" id="btnPrintReceipt" style="margin: 0; flex: 1;">🖨️ Print Receipt</button>
        </div>
    </div>
</div>

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