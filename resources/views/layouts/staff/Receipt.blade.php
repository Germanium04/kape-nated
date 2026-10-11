<x-staff.shell title="Transaction History">

    <main class="wrap">
        <div class="page-head">
            <h1>Branch Transaction History</h1>
            <p class="page-sub">Today's logs, cash audit, and receipts for <strong>{{ $branch ?? 'Your Branch' }}</strong></p>
        </div>

        <!-- 1. SEPARATE FILTERS PANEL -->
        <section class="panel">
            <div class="panel-head">
                <h2>Filter Transactions</h2>
                <span class="panel-note">Refine logs by date range, payment method, or keyword</span>
            </div>
            
            <div class="panel-body">
                <div class="inventory-filter-controls">
                    <div class="inventory-filter-col-item">
                        <label class="inventory-filter-label" for="receiptFilter">Payment</label>
                        <select class="field" id="receiptFilter">
                            <option value="all">All payments</option>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                        </select>
                    </div>

                    <div class="inventory-filter-col-item">
                        <label class="inventory-filter-label" for="receiptRangeMode">View by Range</label>
                        <select class="field" id="receiptRangeMode">
                            <option value="day">Single Day</option>
                            <option value="all">Every Date</option>
                            <option value="month">Month</option>
                            <option value="year">Year</option>
                        </select>
                    </div>

                    <div class="inventory-filter-col-item" id="rangeDayWrap">
                        <label class="inventory-filter-label" for="rangeDay">Date</label>
                        <input type="date" class="field" id="rangeDay" value="{{ \Illuminate\Support\Carbon::today()->format('Y-m-d') }}">
                    </div>

                    <div class="inventory-filter-col-item" id="rangeMonthWrap" hidden>
                        <label class="inventory-filter-label" for="rangeMonth">Month</label>
                        <input type="month" class="field" id="rangeMonth" value="{{ \Illuminate\Support\Carbon::today()->format('Y-m') }}">
                    </div>

                    <div class="inventory-filter-col-item" id="rangeYearWrap" hidden>
                        <label class="inventory-filter-label" for="rangeYear">Year</label>
                        <select class="field" id="rangeYear">
                            <option value="{{ \Illuminate\Support\Carbon::today()->format('Y') }}">{{ \Illuminate\Support\Carbon::today()->format('Y') }}</option>
                            <option value="{{ \Illuminate\Support\Carbon::today()->subYear()->format('Y') }}">{{ \Illuminate\Support\Carbon::today()->subYear()->format('Y') }}</option>
                        </select>
                    </div>

                    <div class="inventory-filter-col-search">
                        <label class="inventory-filter-label" for="receiptSearch">Search</label>
                        <input type="search" class="field" id="receiptSearch" placeholder="Order # or item name...">
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. TRANSACTIONS TABLE PANEL -->
        <section class="panel">
            <div class="panel-head">
                <h2>Transactions</h2>
                <span class="panel-note" id="receiptSummary"></span>
            </div>

            <div class="panel-body table-body-flush">
                <div class="table-wrap">
                    <table class="table" id="receiptTable">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Date &amp; Time</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="ta-r">Total</th>
                                <th class="ta-r">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                @php
                                    $names = implode(', ', array_column($order['items'], 'name'));
                                    $count = array_sum(array_column($order['items'], 'qty'));
                                    $isoDate = \Illuminate\Support\Str::before($order['date'], ' ');
                                @endphp
                                <tr
                                    data-search="{{ strtolower($order['no'].' '.$order['staff'].' '.$names) }}"
                                    data-payment="{{ $order['payment'] }}"
                                    data-date="{{ $isoDate }}"
                                >
                                    <td class="mono"><strong>#{{ $order['no'] }}</strong></td>
                                    <td>{{ $order['date'] }}</td>
                                    <td>{{ $count }} &middot; <span style="color: var(--ink-soft);">{{ $names }}</span></td>
                                    <td><x-staff.badge tone="info">{{ $order['payment'] }}</x-staff.badge></td>
                                    <td><x-staff.badge :tone="$order['status'] === 'Completed' ? 'ok' : 'out'">{{ $order['status'] }}</x-staff.badge></td>
                                    <td class="ta-r mono">₱{{ number_format($order['total'], 2) }}</td>
                                    <td class="ta-r">
                                        <button type="button" class="btn btn--sm btn--ghost" data-open-modal="receipt-modal-{{ $order['no'] }}">View Slip</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="empty-note" id="receiptEmpty" hidden>No transactions found matching your selected filters.</p>
            </div>

            <div class="pagination-bar-wrap">
                <span class="pagination-info-text" id="pageInfo">Showing 1-10 transactions</span>
                <div class="pagination-controls-box">
                    <button type="button" class="btn btn--sm btn--ghost" id="prevPageBtn" disabled>Previous</button>
                    <div id="pageNumbers" class="pagination-numbers-box"></div>
                    <button type="button" class="btn btn--sm btn--ghost" id="nextPageBtn" disabled>Next</button>
                </div>
            </div>
        </section>

        <!-- 3. CASH DRAWER AUDIT PANEL -->
        <section class="panel">
            <div class="panel-head">
                <h2>Cash Drawer Audit</h2>
                <span class="panel-note">Reconcile physical cash counted against system total</span>
            </div>
            <div class="panel-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div>
                        <label class="inventory-filter-label" for="reconExpected">System Cash Takings</label>
                        <input type="text" class="field" id="reconExpected" value="₱0.00" readonly style="font-weight: 700; background: #faf6f0;">
                    </div>
                    <div>
                        <label class="inventory-filter-label" for="reconActual">Physical Cash Counted</label>
                        <input type="number" class="field" id="reconActual" placeholder="e.g. 15000" step="0.01">
                    </div>
                    <div>
                        <label class="inventory-filter-label" for="reconVariance">Variance (Over / Short)</label>
                        <input type="text" class="field" id="reconVariance" value="₱0.00" readonly style="font-weight: 700; background: #faf6f0;">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- RECEIPT SLIP MODALS (same structure as the Order page's success receipt modal) -->
    @foreach($orders as $order)
        <div class="backdrop" id="receipt-modal-{{ $order['no'] }}">
            <div class="modal modal--single" style="width: min(440px, 92vw); max-height: 90vh;">
                <div style="text-align: center; margin-bottom: 12px;">
                    <div style="font-size: 36px; margin-bottom: 4px;">🧾</div>
                    <h2 style="font-size: 18px; margin: 0 0 2px; border-bottom: none; padding: 0;">Transaction Slip</h2>
                    <p style="margin: 0; font-size: 12.5px; color: var(--ink-soft);">Official Order Record</p>
                </div>

                <div class="modal-content-scroll" style="overflow-y: auto; max-height: 50vh;">
                    <div id="print-area-{{ $order['no'] }}" class="receipt-paper">
                        <div class="receipt-paper-head">
                            <h3>Kape-nated</h3>
                            <p class="receipt-branch">{{ $order['branch'] }}</p>
                            <p class="receipt-date">{{ $order['date'] }}</p>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--line-soft);">
                            <span>Order No. <b>#{{ $order['no'] }}</b></span>
                            <span>Payment: <b>{{ strtoupper($order['payment']) }}</b></span>
                        </div>

                        <div class="receipt-paper-body">
                            @foreach($order['items'] as $item)
                                @php
                                    $sizeTemp = implode(' · ', array_filter([$item['sizeLabel'] ?? null, $item['temp'] ?? null]));
                                @endphp
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 13px;">
                                    <span>
                                        {{ $item['qty'] }}x {{ $item['name'] }}
                                        @if($sizeTemp)
                                            <br><small style="color: var(--ink-soft); padding-left: 8px;">{{ ucfirst($sizeTemp) }}</small>
                                        @endif
                                        @if(!empty($item['addons']))
                                            <br><small style="color: var(--ink-soft); padding-left: 8px;">+ {{ implode(', ', $item['addons']) }}</small>
                                        @endif
                                    </span>
                                    <span class="mono">₱{{ number_format($item['price'] * $item['qty'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="receipt-paper-footer">
                            <div class="receipt-row-total">
                                <span>Total Amount</span>
                                <strong style="font-family: 'Baloo 2', sans-serif; font-size: 20px;">₱{{ number_format($order['total'], 2) }}</strong>
                            </div>
                            <div class="receipt-row-sub" style="margin-top: 6px;">
                                <span>Cashier</span>
                                <span>{{ $order['staff'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 14px;">
                    <button type="button" class="btn btn--ghost" data-close-modal="receipt-modal-{{ $order['no'] }}" style="flex: 1; margin: 0;">Done</button>
                    <button type="button" class="btn btn--primary" onclick="printReceipt('print-area-{{ $order['no'] }}')" style="flex: 1; margin: 0;">🖨️ Print Receipt</button>
                </div>
            </div>
        </div>
    @endforeach

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initStaffReceipts();
        });
    </script>
    @endpush

@extends('layouts.staff.Shell')
</x-staff.shell>