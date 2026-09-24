@php
    use App\Support\DemoData;

    $cashWeek  = array_sum(array_column($sales, 'cash'));
    $gcashWeek = array_sum(array_column($sales, 'gcash'));
    $weekTotal = $cashWeek + $gcashWeek;
@endphp

<x-admin.shell title="Sales" heading="Sales" subheading="Takings, payment mix, and what's actually moving">

    <x-admin.panel title="Filter" note="Combine a branch with a range">
        <div class="filter-row">
            <label class="field-group">
                <span>Branch</span>
                <select class="field" id="salesBranch">
                    <option value="all">All branches</option>
                    @foreach(array_keys(DemoData::branchWeights()) as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-group">
                <span>View by</span>
                <select class="field" id="salesRangeMode">
                    <option value="day">A single day</option>
                    <option value="month">A month</option>
                    <option value="year">A year</option>
                </select>
            </label>

            <label class="field-group" id="salesDayWrap">
                <span>Date</span>
                <input type="date" class="field" id="salesDay" value="2026-09-19">
            </label>

            <label class="field-group" id="salesMonthWrap" hidden>
                <span>Month</span>
                <input type="month" class="field" id="salesMonth" value="2026-09">
            </label>

            <label class="field-group" id="salesYearWrap" hidden>
                <span>Year</span>
                <select class="field" id="salesYear">
                    <option value="2026">2026</option>
                    <option value="2025">2025</option>
                </select>
            </label>
        </div>
        <p class="panel-intro" style="margin-top:10px">
            Only <strong>13–19 September 2026</strong> for now
        </p>
    </x-admin.panel>

    <div class="stat-row" id="salesStats"></div>

    <div class="grid-2">
        <x-admin.panel title="Daily takings" note="Last 7 recorded days, scaled to the branch above">
            <div class="chart" id="salesChart"></div>
            <p class="legend"><span class="dot dot--cash"></span>Cash <span class="dot dot--gcash"></span>GCash</p>
        </x-admin.panel>

        <x-admin.panel title="How people paid" note="Same 7 days">
            <div class="split" id="salesSplit"></div>

            <h3 class="sub-head">Sales per branch</h3>
            <p class="muted" style="margin:0 0 8px;font-size:13px">For the range you picked above · longest bar is your top branch, not a target</p>
            <ul class="rank rank--plain" id="branchRanking"></ul>
        </x-admin.panel>
    </div>

    <x-admin.panel title="Menu performance" note="Last 7 days, scaled to the branch above">
        <table class="table">
            <thead>
                <tr><th>Drink</th><th class="ta-r">Cups sold</th><th class="ta-r">Revenue</th><th class="ta-r">Share</th><th>Vs. top seller</th></tr>
            </thead>
            <tbody id="menuPerformance"></tbody>
        </table>
    </x-admin.panel>

    <x-admin.panel title="Day by day" note="13–19 September 2026, actual figures — not affected by the filter above">
        <table class="table">
            <thead>
                <tr><th>Day</th><th class="ta-r">Cash</th><th class="ta-r">GCash</th><th class="ta-r">Total</th></tr>
            </thead>
            <tbody>
                @foreach(array_reverse($sales) as $day)
                    <tr>
                        <td>{{ $day['label'] }}</td>
                        <td class="ta-r mono">₱{{ number_format($day['cash']) }}</td>
                        <td class="ta-r mono">₱{{ number_format($day['gcash']) }}</td>
                        <td class="ta-r mono"><b>₱{{ number_format($day['cash'] + $day['gcash']) }}</b></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td>Week</td><td class="ta-r mono">₱{{ number_format($cashWeek) }}</td><td class="ta-r mono">₱{{ number_format($gcashWeek) }}</td><td class="ta-r mono">₱{{ number_format($weekTotal) }}</td></tr>
            </tfoot>
        </table>
    </x-admin.panel>

    @push('scripts')
        <script>
            window.KAPE_SALES = {
                daily: @json($sales),
                topItems: @json($topItems),
                branchWeights: @json(DemoData::branchWeights()),
            };
            initSales();
        </script>
    @endpush

</x-admin.shell>