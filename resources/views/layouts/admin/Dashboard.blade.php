@php
    $todayDate   = \Illuminate\Support\Carbon::today()->format('Y-m-d');
    $today       = array_values(array_filter($orders, fn ($o) => str_starts_with($o['date'], $todayDate)));
    $salesToday  = array_sum(array_map(fn ($o) => $o['status'] === 'Completed' ? ($o['total'] ?? 0) : 0, $today));
    $ordersToday = count($today);
    $avgTicket   = $ordersToday > 0 ? $salesToday / $ordersToday : 0;

    $lowStock = array_values(array_filter($ingredients, fn ($i) => $i['stock'] <= $i['reorder']));
    $week     = array_sum(array_map(fn ($d) => $d['cash'] + $d['gcash'], $sales));
@endphp

<x-admin.shell title="Dashboard" heading="Good afternoon, boss" subheading="Friday, 19 September 2026 · store open since 7:00 AM">

    <div class="stat-row">
        <x-admin.stat label="Sales today"   value="₱{{ number_format($salesToday, 2) }}" trend="+14% vs yesterday" tone="up" />
        <x-admin.stat label="Orders today"  value="{{ $ordersToday }}" trend="3 in the last hour" />
        <x-admin.stat label="Gross Income" value="₱{{ number_format($avgTicket, 2) }}" trend="+₱22 vs last week" tone="up" />
        <x-admin.stat label="Items to reorder" value="{{ count($lowStock) }}" trend="Check the stock room" tone="alert" onclick/>
    </div>

    @if($lowStock)
        <x-admin.panel tone="warning" title="Running low" note="Based on what's left after today's orders">
            <ul class="alert-list">
                @foreach($lowStock as $item)
                    @php $daysLeft = $item['used_today'] > 0 ? floor($item['stock'] / $item['used_today']) : null; @endphp
                    <li>
                        <span class="alert-name">{{ $item['name'] }}</span>
                        <span class="alert-figure">{{ number_format($item['stock']) }}{{ $item['unit'] }} left, reorder at {{ number_format($item['reorder']) }}{{ $item['unit'] }}</span>
                        <x-admin.badge :tone="$item['stock'] == 0 ? 'out' : 'low'">
                            @if($daysLeft === null) No usage yet
                            @elseif($daysLeft < 1) Runs out today
                            @else About {{ $daysLeft }} {{ $daysLeft == 1 ? 'day' : 'days' }} left
                            @endif
                        </x-admin.badge>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('admin.inventory') }}" class="btn btn--primary">Open inventory</a>
        </x-admin.panel>
    @endif

    <div class="grid-2">
        <x-admin.panel title="This week" note="₱{{ number_format($week) }} total">
            <div class="chart">
                @php 
                    $rawMax = max(array_merge([0], array_map(fn ($d) => $d['cash'] + $d['gcash'], $sales))); 
                    $max = $rawMax > 0 ? $rawMax : 1; 
                @endphp
                @foreach($sales as $day)
                    @php 
                        $sum = $day['cash'] + $day['gcash']; 
                        $height = round(($sum / $max) * 100);
                        $gcashHeight = $sum > 0 ? round(($day['gcash'] / $sum) * 100) : 0;
                        $cashHeight  = $sum > 0 ? round(($day['cash'] / $sum) * 100) : 0;
                    @endphp
                    <div class="chart-col" title="{{ $day['label'] }}: ₱{{ number_format($sum) }}">
                        <div class="chart-stack" style="height: {{ $height }}%">
                            <div class="chart-seg chart-seg--gcash" style="height: {{ $gcashHeight }}%"></div>
                            <div class="chart-seg chart-seg--cash"  style="height: {{ $cashHeight }}%"></div>
                        </div>
                        <span class="chart-label">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="legend"><span class="dot dot--cash"></span>Cash <span class="dot dot--gcash"></span>GCash</p>
        </x-admin.panel>

        <x-admin.panel title="Best sellers" note="Last 7 days · bar shows size vs. top seller">
            <ol class="rank">
                @foreach($topItems as $item)
                    <li>
                        <span class="rank-name">{{ $item['name'] }}</span>
                        <span class="rank-bar"><i style="width: {{ round($item['sold'] / $topItems[0]['sold'] * 100) }}%"></i></span>
                        <span class="rank-num">{{ $item['sold'] }} cups</span>
                    </li>
                @endforeach
            </ol>
        </x-admin.panel>
    </div>

    <x-admin.panel title="Today's orders" note="Pulled live from staff terminals">
        <table class="table" id="dashboardOrdersTable">
            <thead>
                <tr><th>Order</th><th>Time</th><th>Cashier</th><th>Branch</th><th>Quantity</th><th>Payment</th><th class="ta-r">Total</th><th>Action</th></tr>
            </thead>
            <tbody>
                @foreach(array_slice($orders, 0, 5) as $order)
                    <tr>
                        <td class="mono">#{{ $order['no'] }}</td>
                        <td>{{ \Illuminate\Support\Str::after($order['date'], ' ') }}</td>
                        <td>{{ $order['staff'] }}</td>
                        <td>{{ $order['branch'] }}</td>
                        <td>{{ array_sum(array_column($order['items'], 'qty')) }}</td>
                        <td>{{ $order['payment'] }}</td>
                        <td class="ta-r mono">₱{{ number_format($order['total'], 2) }}</td>
                        <td class="ta-r"><button type="button" class="btn btn--ghost btn--sm" data-open-modal="receipt-{{ $order['no'] }}">View</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div id="dashboardPagination" class="pagination-bar" hidden>
            <span id="dashboardPageInfo" class="muted" style="font-size: 13px;"></span>
            <div class="pagination-controls" style="display: flex; gap: 6px; align-items: center;">
                <button type="button" id="dashPrevBtn" class="btn btn--ghost btn--sm">&laquo; Prev</button>
                <div id="dashPageNumbers" style="display: flex; gap: 4px;"></div>
                <button type="button" id="dashNextBtn" class="btn btn--ghost btn--sm">Next &raquo;</button>
            </div>
        </div>

        @foreach($orders as $order)
            <x-admin.modal id="receipt-{{ $order['no'] }}" title="Order #{{ $order['no'] }}" size="sm">
                <x-admin.receipt :order="$order" />

                <x-slot:footer>
                    <button type="button" class="btn btn--ghost" data-close-modal="receipt-{{ $order['no'] }}">Close</button>
                    <button type="button" class="btn btn--primary" onclick="window.print()">Print receipt</button>
                </x-slot:footer>
            </x-admin.modal>
        @endforeach
    </x-admin.panel>

</x-admin.shell>