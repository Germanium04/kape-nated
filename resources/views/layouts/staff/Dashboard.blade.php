@php
    use App\Support\DemoData;

    $today       = array_values(array_filter($orders, fn ($o) => str_starts_with($o['date'], '2026-09-19')));
    $salesToday  = array_sum(array_map(fn ($o) => $o['status'] === 'Completed' ? DemoData::total($o) : 0, $today));
    $ordersToday = count($today);

    $lowStock = array_values(array_filter($ingredients, fn ($i) => $i['stock'] <= $i['reorder']));
    $week     = array_sum(array_map(fn ($d) => $d['cash'] + $d['gcash'], $sales));
@endphp

<x-staff.shell title="Dashboard" heading="Dashboard" subheading="Overview of your daily activities">
    <div class="stat-row">
        <x-admin.stat label="Sales today"   value="₱{{ number_format($salesToday, 2) }}" trend="+14% vs yesterday" tone="up" />
        <x-admin.stat label="Orders today"  value="{{ $ordersToday }}" trend="3 in the last hour" />
        <x-admin.stat label="Items to reorder" value="{{ count($lowStock) }}" trend="Check the stock room" tone="alert" />
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
            <a href="{{ route('staff.inventory') }}" class="btn btn--primary">Open inventory</a>
        </x-admin.panel>
    @endif

    <x-admin.panel title="Latest orders" note="Pulled from the staff terminal">
        <table class="table">
            <thead>
                <tr><th>Order</th><th>Time</th><th>Cashier</th><th>Items</th><th>Payment</th><th class="ta-r">Total</th><th></th></tr>
            </thead>
            <tbody>
                @foreach(array_slice($orders, 0, 5) as $order)
                    <tr>
                        <td class="mono">#{{ $order['no'] }}</td>
                        <td>{{ \Illuminate\Support\Str::after($order['date'], ' ') }}</td>
                        <td>{{ $order['staff'] }}</td>
                        <td>{{ array_sum(array_column($order['items'], 'qty')) }}</td>
                        <td>{{ $order['payment'] }}</td>
                        <td class="ta-r mono">₱{{ number_format(DemoData::total($order), 2) }}</td>
                        <td class="ta-r"><a class="link" href="{{ route('admin.receipts') }}">Receipt</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin.panel>
</x-staff.shell>