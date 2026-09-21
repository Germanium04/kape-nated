@php use App\Support\DemoData; @endphp

<x-admin.shell title="Receipts" heading="Transaction history" subheading="Every order closed out across your branches, pulled from the staff terminal">

    <x-admin.panel title="Filter" note="Pick a branch and a range, then search within it">
        <div class="filter-row">
            <label class="field-group">
                <span>Branch</span>
                <select class="field" id="receiptBranch">
                    <option value="all">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-group">
                <span>Payment</span>
                <select class="field" id="receiptFilter">
                    <option value="all">All payments</option>
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                </select>
            </label>

            <label class="field-group">
                <span>View by</span>
                <select class="field" id="receiptRangeMode">
                    <option value="all">Every date</option>
                    <option value="day">A single day</option>
                    <option value="month">A month</option>
                    <option value="year">A year</option>
                </select>
            </label>

            <label class="field-group" id="rangeDayWrap" hidden>
                <span>Date</span>
                <input type="date" class="field" id="rangeDay" value="2026-09-19">
            </label>

            <label class="field-group" id="rangeMonthWrap" hidden>
                <span>Month</span>
                <input type="month" class="field" id="rangeMonth" value="2026-09">
            </label>

            <label class="field-group" id="rangeYearWrap" hidden>
                <span>Year</span>
                <select class="field" id="rangeYear">
                    <option value="2026">2026</option>
                    <option value="2025">2025</option>
                </select>
            </label>

            <label class="field-group field-group--grow">
                <span>Search</span>
                <input type="search" class="field" id="receiptSearch" placeholder="Order no., cashier, or item">
            </label>
        </div>
    </x-admin.panel>

    <x-admin.panel title="Transactions" note="">
        <x-slot:tools>
            <span id="receiptSummary" class="panel-note"></span>
        </x-slot:tools>

        <table class="table table--click" id="receiptTable">
            <thead>
                <tr><th>Order</th><th>Date &amp; time</th><th>Branch</th><th>Cashier</th><th>Items</th><th>Payment</th><th>Status</th><th class="ta-r">Total</th><th></th></tr>
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
                        data-branch="{{ $order['branch'] }}"
                        data-date="{{ $isoDate }}"
                    >
                        <td class="mono">#{{ $order['no'] }}</td>
                        <td>{{ $order['date'] }}</td>
                        <td>{{ $order['branch'] }}</td>
                        <td>{{ $order['staff'] }}</td>
                        <td class="cell-items">{{ $count }} &middot; <span class="muted">{{ $names }}</span></td>
                        <td>{{ $order['payment'] }}</td>
                        <td><x-admin.badge :tone="$order['status'] === 'Completed' ? 'ok' : 'out'">{{ $order['status'] }}</x-admin.badge></td>
                        <td class="ta-r mono">₱{{ number_format(DemoData::total($order), 2) }}</td>
                        <td class="ta-r"><button type="button" class="btn btn--ghost btn--sm" data-open-modal="receipt-{{ $order['no'] }}">View</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="empty-note" id="receiptEmpty" hidden>No transaction matches that branch, range, or search. Widen the filter to see more.</p>
    </x-admin.panel>

    @foreach($orders as $order)
        <x-admin.modal id="receipt-{{ $order['no'] }}" title="Order #{{ $order['no'] }}" size="sm">
            <x-admin.receipt :order="$order" />

            <x-slot:footer>
                <button type="button" class="btn btn--ghost" data-close-modal="receipt-{{ $order['no'] }}">Close</button>
                <button type="button" class="btn btn--primary" onclick="window.print()">Print receipt</button>
            </x-slot:footer>
        </x-admin.modal>
    @endforeach

</x-admin.shell>