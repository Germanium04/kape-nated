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

<!-- Order Pre-Confirmation Dialogue Modal -->
<div class="backdrop" id="confirmOrderModal">
    <div class="modal modal--single">
        <div style="text-align: center;">
            <div style="font-size: 32px; margin-bottom: 6px;">☕</div>
            <h2 style="font-size: 18px; margin: 0 0 4px; border-bottom: none; padding: 0;">Confirm Order</h2>
            <p style="margin: 0 0 12px; font-size: 12.5px; color: var(--ink-soft);">Please review order total before processing</p>

            <div style="margin: 12px 0; padding: 14px; background: var(--bg-soft, #fdf6ee); border-radius: 8px; border: 1px solid var(--line-soft, #eee);">
                <p style="margin: 0; font-size: 11.5px; color: var(--ink-soft); text-transform: uppercase; letter-spacing: 0.5px;">Total Amount Due</p>
                <p id="confirmModalTotal" style="font-size: 26px; font-weight: 700; color: var(--bar); margin: 4px 0 0;">₱0.00</p>
                <p id="confirmModalPayType" style="font-size: 12px; margin: 4px 0 0; font-weight: 600; text-transform: uppercase; color: var(--ink-soft);"></p>
            </div>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 14px;">
            <button type="button" class="btn btn--ghost" id="btnCancelOrderConfirm" style="flex: 1; margin: 0;">Cancel</button>
            <button type="button" class="btn btn--primary" id="btnSubmitOrderConfirm" style="flex: 1; margin: 0;">Okay, Complete</button>
        </div>
    </div>
</div>

<!-- Order Success Receipt Dialogue Modal -->
<div class="backdrop" id="receiptModal">
    <div class="modal modal--single" style="width: min(440px, 92vw); max-height: 90vh;">
        <div style="text-align: center; margin-bottom: 12px;">
            <div style="font-size: 36px; margin-bottom: 4px;">✅</div>
            <h2 style="font-size: 18px; margin: 0 0 2px; border-bottom: none; padding: 0; color: var(--good, #46703f);">Order Completed!</h2>
            <p id="rcptMeta" style="margin: 0; font-size: 12.5px; color: var(--ink-soft);"></p>
        </div>

        <div class="modal-content-scroll" style="overflow-y: auto; max-height: 50vh;">
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

        <div style="display: flex; gap: 8px; margin-top: 14px;">
            <button type="button" class="btn btn--ghost" data-close-modal="receiptModal" style="flex: 1; margin: 0;">Done</button>
            <button type="button" class="btn btn--primary" id="btnPrintReceipt" style="flex: 1; margin: 0;">🖨️ Print Receipt</button>
        </div>
    </div>
</div>

<!-- Order Error Dialogue Modal -->
<div class="backdrop" id="orderErrorModal">
    <div class="modal modal--single" style="text-align: center;">
        <div style="font-size: 36px; margin-bottom: 6px;">⚠️</div>
        <h2 style="font-size: 18px; margin: 0 0 4px; border-bottom: none; padding: 0; color: var(--flag, #a63d2a);" id="orderErrorTitle">Transaction Error</h2>
        <p style="margin: 6px 0 14px; font-size: 13px; color: var(--ink-soft);" id="orderErrorText">An unexpected error occurred while processing the order.</p>

        <div style="margin-top: 8px;">
            <button type="button" class="btn btn--primary" data-close-modal="orderErrorModal" style="margin: 0; width: 100%;">Understood</button>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/Staff.js') }}"></script>
    <script>
        window.KAPE_ORDER = {
            menu:     @json($menu),
            addons:   @json($addons),
            storeUrl: @json(route('staff.orders.store')),
        };
        document.addEventListener('DOMContentLoaded', () => {
            initStaffOrder();
        });
    </script>
@endpush


</x-staff.shell>