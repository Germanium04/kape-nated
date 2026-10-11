<x-staff.shell title="Transaction History">

    <style>
        .history-table-wrap { overflow-x: auto; }
        .history-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .history-table th, .history-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--line-soft); vertical-align: top; }
        .history-table thead th { font-size: 12.5px; text-transform: uppercase; letter-spacing: .4px; color: var(--ink-soft); border-bottom: 1px solid var(--line); }
        .history-table tbody tr:hover { background: rgba(0,0,0,.015); }
        .mono { font-variant-numeric: tabular-nums; font-family: ui-monospace, monospace; }
        .ta-r { text-align: right; }
        .muted { color: var(--ink-soft); }
        .cell-items { max-width: 280px; }
        .history-filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .history-filters .fg { display: flex; flex-direction: column; gap: 6px; }
        .history-filters .fg-grow { flex: 1; min-width: 200px; }
        .history-filters label span { font-size: 13px; font-weight: 500; }
        .field { width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: #fff; color: var(--ink); box-sizing: border-box; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge--ok { background: var(--good-pale); color: var(--good); }
        .badge--out { background: var(--flag-pale); color: var(--flag); }
        .badge--demo { background: var(--tan-pale); color: var(--tan-dark); margin-left: 6px; }
        .empty-note { color: var(--ink-soft); text-align: center; padding: 24px 0; }
        .pagination-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 14px; }
        .pagination-controls-group { display: flex; gap: 6px; align-items: center; }
        .pagination-numbers-group { display: flex; gap: 4px; }
    </style>

    <main class="wrap">
        <div class="page-head">
            <h1>Transaction History</h1>
            <p class="page-sub">{{ $branch ?? 'No branch assigned' }} · every sale rung up at your branch</p>
        </div>

        @unless($branch)
            <section class="panel panel--warning">
                <div class="panel-head"><h2>No branch assigned</h2></div>
                <div class="panel-body">This account isn't linked to a branch yet, so there is nothing to show. Ask an admin to assign one.</div>
            </section>
        @else
            <div class="panel">
                <div class="panel-body">
                    <div class="history-filters">
                        <div class="fg">
                            <label for="historyPayment"><span>Payment</span></label>
                            <select class="field" id="historyPayment">
                                <option value="all">All payments</option>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="historyRangeMode"><span>View by</span></label>
                            <select class="field" id="historyRangeMode">
                                <option value="all">Every date</option>
                                <option value="day">A single day</option>
                                <option value="month">A month</option>
                                <option value="year">A year</option>
                            </select>
                        </div>
                        <div class="fg" id="historyDayWrap" hidden>
                            <label for="historyDay"><span>Date</span></label>
                            <input type="date" class="field" id="historyDay" value="{{ \Illuminate\Support\Carbon::today()->format('Y-m-d') }}">
                        </div>
                        <div class="fg" id="historyMonthWrap" hidden>
                            <label for="historyMonth"><span>Month</span></label>
                            <select class="field" id="historyMonth">
                                @for($m = 0; $m < 12; $m++)
                                    @php $d = \Illuminate\Support\Carbon::today()->startOfMonth()->subMonths($m); @endphp
                                    <option value="{{ $d->format('Y-m') }}">{{ $d->format('F Y') }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="fg" id="historyYearWrap" hidden>
                            <label for="historyYear"><span>Year</span></label>
                            <select class="field" id="historyYear">
                                @for($y = 0; $y < 5; $y++)
                                    @php $yr = \Illuminate\Support\Carbon::today()->subYears($y)->format('Y'); @endphp
                                    <option value="{{ $yr }}">{{ $yr }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="fg fg-grow">
                            <label for="historySearch"><span>Search</span></label>
                            <input type="search" class="field" id="historySearch" placeholder="Order no., cashier, or item">
                        </div>
                    </div>
                </div>
            </div>

            <section class="panel">
                <div class="panel-head">
                    <h2>Transactions</h2>
                    <span class="panel-note" id="historySummary"></span>
                </div>
                <div class="panel-body">
                    <div class="history-table-wrap">
                        <table class="history-table" id="historyTable">
                            <thead>
                                <tr><th>Order</th><th>Date &amp; time</th><th>Cashier</th><th>Items</th><th>Payment</th><th>Status</th><th class="ta-r">Total</th><th>Action</th></tr>
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
                                        <td class="mono">#{{ $order['no'] }}@if($order['source'] === 'demo')<span class="badge badge--demo">Demo</span>@endif</td>
                                        <td>{{ $order['date'] }}</td>
                                        <td>{{ $order['staff'] }}</td>
                                        <td class="cell-items">{{ $count }} &middot; <span class="muted">{{ $names }}</span></td>
                                        <td>{{ $order['payment'] }}</td>
                                        <td><span class="badge {{ $order['status'] === 'Completed' ? 'badge--ok' : 'badge--out' }}">{{ $order['status'] }}</span></td>
                                        <td class="ta-r mono">₱{{ number_format($order['total'], 2) }}</td>
                                        <td class="ta-r"><button type="button" class="btn btn--ghost btn--sm" data-open-modal="history-{{ $order['uid'] }}">View</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="empty-note" id="historyEmpty" hidden>No transaction matches that filter or search. Widen the filter to see more.</p>

                    <div class="pagination-bar" id="historyPagination">
                        <span class="muted" id="historyPageInfo">Showing 1-10 of 0 transactions</span>
                        <div class="pagination-controls-group">
                            <button type="button" class="btn btn--ghost btn--sm" id="historyPrevBtn" disabled>Previous</button>
                            <div id="historyPageNumbers" class="pagination-numbers-group"></div>
                            <button type="button" class="btn btn--ghost btn--sm" id="historyNextBtn" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </section>

            <style>
                .receipt { background: #fff; border: 1px dashed var(--line-soft); border-radius: var(--radius-sm); padding: 16px; }
                .receipt-top { text-align: center; border-bottom: 1px dashed var(--line-soft); padding-bottom: 10px; margin-bottom: 10px; }
                .receipt-brand { font-size: 18px; margin: 0; }
                .receipt-addr { font-size: 12px; color: var(--ink-soft); margin: 2px 0 0; }
                .receipt-meta { margin: 0 0 10px; }
                .receipt-meta div { display: flex; justify-content: space-between; gap: 8px; font-size: 13.5px; padding: 2px 0; }
                .receipt-meta dt { color: var(--ink-soft); }
                .receipt-meta dd { margin: 0; font-weight: 600; text-align: right; }
                .receipt-lines { width: 100%; border-collapse: collapse; margin: 10px 0; }
                .receipt-lines td { padding: 4px 0; vertical-align: top; font-size: 13.5px; }
                .rl-qty { width: 40px; color: var(--ink-soft); }
                .rl-opt { display: block; color: var(--ink-soft); font-size: 12px; }
                .rl-amt { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
                .receipt-sums { border-top: 1px dashed var(--line-soft); padding-top: 8px; font-size: 13.5px; }
                .receipt-sums p { display: flex; justify-content: space-between; margin: 2px 0; }
                .receipt-total { font-size: 17px; font-weight: 700; }
                .receipt-foot { text-align: center; color: var(--ink-soft); font-size: 13px; margin: 10px 0 0; }
            </style>

            @foreach($orders as $order)
                @php
                    $rSubtotal = 0;
                    foreach ($order['items'] as $line) {
                        $rSubtotal += $line['qty'] * ($line['price'] + count($line['addons']) * 20);
                    }
                    $rVat = round($rSubtotal * 0.12, 2);
                @endphp
                <x-staff.modal :id="'history-' . $order['uid']">
                    <h2 id="history-{{ $order['uid'] }}-title">Receipt #{{ $order['no'] }}</h2>
                    <div class="receipt">
                        <div class="receipt-top">
                            <p class="receipt-brand">kape&#8209;nated</p>
                            <p class="receipt-addr">Mabini, Davao de Oro &middot; 0917 000 0000</p>
                        </div>
                        <dl class="receipt-meta">
                            <div><dt>Order no.</dt><dd>{{ $order['no'] }}</dd></div>
                            <div><dt>Date</dt><dd>{{ $order['date'] }}</dd></div>
                            <div><dt>Branch</dt><dd>{{ $order['branch'] }}</dd></div>
                            <div><dt>Cashier</dt><dd>{{ $order['staff'] }}</dd></div>
                            <div><dt>Payment</dt><dd>{{ $order['payment'] }}</dd></div>
                            @if(strtolower($order['payment'] ?? '') === 'gcash')
                                <div><dt>GCash Ref</dt><dd>{{ $order['gcash_ref'] ?? '—' }}</dd></div>
                            @endif
                        </dl>
                        <table class="receipt-lines">
                            @foreach($order['items'] as $line)
                                <tr>
                                    <td class="rl-qty">{{ $line['qty'] }}&times;</td>
                                    <td class="rl-name">
                                        {{ $line['name'] }}
                                        <span class="rl-opt">{{ $line['temp'] }}@if($line['addons']), {{ implode(', ', $line['addons']) }}@endif</span>
                                    </td>
                                    <td class="rl-amt">{{ number_format($line['qty'] * ($line['price'] + count($line['addons']) * 20), 2) }}</td>
                                </tr>
                            @endforeach
                        </table>
                        <div class="receipt-sums">
                            <p><span>Subtotal</span><span>₱{{ number_format($rSubtotal, 2) }}</span></p>
                            <p><span>VAT included (12%)</span><span>₱{{ number_format($rVat, 2) }}</span></p>
                            <p class="receipt-total"><span>Total</span><span>₱{{ number_format($rSubtotal, 2) }}</span></p>
                        </div>
                        <p class="receipt-foot">Salamat! Balik kayo.</p>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 14px;">
                        <button type="button" class="btn btn--ghost" data-close-modal="history-{{ $order['uid'] }}" style="flex: 1; margin: 0;">Close</button>
                        <button type="button" class="btn btn--primary" onclick="window.print()" style="flex: 1; margin: 0;">Print receipt</button>
                    </div>
                </x-staff.modal>
            @endforeach
        @endunless
    </main>

    @push('scripts')
    <script src="{{ asset('js/staff-history.js') }}"></script>
    @endpush

</x-staff.shell>
