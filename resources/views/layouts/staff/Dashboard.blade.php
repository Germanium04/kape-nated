@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $num  = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $pay  = fn ($p) => $p === 'gcash' ? 'GCash' : 'Cash';
    $tone = fn ($s) => ['completed' => 'ok', 'refunded' => 'low', 'voided' => 'muted'][$s] ?? 'ok';

    $first = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
    $hour  = now()->hour;
    $greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $day   = $isToday ? 'today' : 'that day';

    $flagged   = $dayOrders->where('status', '!=', 'completed')->count();
    $weekTotal = $week->sum(fn ($d) => $d['cash'] + $d['gcash']);
    $weekMax   = max(1, $week->max(fn ($d) => $d['cash'] + $d['gcash']));
    $splitSum  = max(1, $split['cash'] + $split['gcash']);
    $topMax    = max(1, $topItems->first()['sold'] ?? 1);
@endphp

<x-staff.shell title="Dashboard">

<main class="wrap">
    <div class="page-head">
        <h1>{{ $greet }}, {{ $first }}</h1>
        <p class="page-sub">{{ $branch ?? 'No branch assigned' }} · {{ $date->format('l, j F Y') }}</p>
    </div>

    @unless($branch)
        <section class="panel panel--warning">
            <div class="panel-head"><h2>No branch assigned</h2></div>
            <div class="panel-body">This account isn't linked to a branch yet, so there is nothing to show. Ask an admin to assign one.</div>
        </section>
    @endunless

    <div class="stat-row">
        <x-staff.stat label="Sales {{ $day }}" value="{{ $peso($sales) }}"
            :trend="$delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '% vs the day before' : null"
            :tone="$delta === null || $delta == 0 ? 'neutral' : ($delta > 0 ? 'up' : 'down')" />
        <x-staff.stat label="Orders {{ $day }}" value="{{ $count }}"
            :trend="$flagged ? $flagged . ' refunded or voided' : null" />
        <x-staff.stat label="Average ticket" value="{{ $peso($avg) }}" />
        <x-staff.stat label="Items to reorder" value="{{ $lowStock->count() }}"
            :trend="$lowStock->count() ? 'Check the stock room' : null"
            :tone="$lowStock->count() ? 'alert' : 'neutral'" />
    </div>

    @if($lowStock->isNotEmpty())
        <section class="panel panel--warning">
            <div class="panel-head">
                <h2>Running low</h2>
                <span class="panel-note">Based on what was used so far today</span>
            </div>
            <div class="panel-body">
                <ul class="alert-list">
                    @foreach($lowStock as $item)
                        <li>
                            <span class="alert-name">{{ $item->name }}</span>
                            <span class="alert-figure">{{ $num($item->stock) }}{{ $item->unit }} left, reorder at {{ $num($item->reorder_level) }}{{ $item->unit }}</span>
                            <x-staff.badge :tone="$item->state">
                                @if($item->state === 'out') Out of stock
                                @elseif($item->days_left === null) No usage yet
                                @elseif($item->days_left < 1) Runs out today
                                @else About {{ $item->days_left }} {{ $item->days_left == 1 ? 'day' : 'days' }} left
                                @endif
                            </x-staff.badge>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('staff.inventory') }}" class="btn btn--sm">Open inventory</a>
            </div>
        </section>
    @endif

    <div class="grid-2">
        <section class="panel">
            <div class="panel-head">
                <h2>Last 7 days</h2>
                <span class="panel-note">{{ $peso($weekTotal) }} total</span>
            </div>
            <div class="panel-body">
                <div class="chart">
                    @foreach($week as $d)
                        @php $sum = $d['cash'] + $d['gcash']; @endphp
                        <div class="chart-col" title="{{ $d['label'] }}: {{ $peso($sum) }}">
                            <div class="chart-stack" style="height: {{ round($sum / $weekMax * 100) }}%">
                                @if($sum > 0)
                                    <div class="chart-seg chart-seg--gcash" style="height: {{ round($d['gcash'] / $sum * 100) }}%"></div>
                                    <div class="chart-seg chart-seg--cash"  style="height: {{ round($d['cash']  / $sum * 100) }}%"></div>
                                @endif
                            </div>
                            <span class="chart-label">{{ $d['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="legend"><span class="dot dot--cash"></span>Cash <span class="dot dot--gcash"></span>GCash</p>
            </div>
        </section>

        <div class="stack">
            <section class="panel">
                <div class="panel-head">
                    <h2>Payments {{ $day }}</h2>
                    <span class="panel-note">Completed orders only</span>
                </div>
                <div class="panel-body">
                    <div class="split-bar" role="img" aria-label="Cash {{ round($split['cash'] / $splitSum * 100) }} percent, GCash {{ round($split['gcash'] / $splitSum * 100) }} percent">
                        <span class="split-seg--cash"  style="width: {{ $split['cash']  / $splitSum * 100 }}%"></span>
                        <span class="split-seg--gcash" style="width: {{ $split['gcash'] / $splitSum * 100 }}%"></span>
                    </div>
                    <div class="pay-cards">
                        <div class="pay-card pay-card--cash">
                            <span class="pay-card-label"><span class="dot dot--cash"></span>Cash</span>
                            <span class="pay-card-amount">{{ $peso($split['cash']) }}</span>
                            <span class="pay-card-pct">{{ $sales > 0 ? round($split['cash'] / $splitSum * 100) : 0 }}% of sales</span>
                        </div>
                        <div class="pay-card pay-card--gcash">
                            <span class="pay-card-label"><span class="dot dot--gcash"></span>GCash</span>
                            <span class="pay-card-amount">{{ $peso($split['gcash']) }}</span>
                            <span class="pay-card-pct">{{ $sales > 0 ? round($split['gcash'] / $splitSum * 100) : 0 }}% of sales</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Best sellers</h2>
                    <span class="panel-note">Last 7 days · this branch</span>
                </div>
                <div class="panel-body">
                    @if($topItems->isEmpty())
                        <p class="empty-note">No sales yet in this period.</p>
                    @else
                        <ol class="rank">
                            @foreach($topItems as $item)
                                <li>
                                    <span class="rank-name">{{ $item['name'] }}</span>
                                    <span class="rank-bar"><i style="width: {{ round($item['sold'] / $topMax * 100) }}%"></i></span>
                                    <span class="rank-num">{{ $item['sold'] }} cups</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2>Receipts</h2>
            <span class="panel-note">{{ $dayOrders->count() }} {{ \Illuminate\Support\Str::plural('order', $dayOrders->count()) }} · {{ $date->format('M j, Y') }}</span>
            <div class="panel-tools">
                <form method="GET">
                    <input type="date" name="date" class="field" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()" aria-label="Show receipts for day">
                    @unless($isToday)
                        <a href="{{ route('staff.dashboard') }}" class="btn btn--ghost btn--sm">Back to today</a>
                    @endunless
                </form>
            </div>
        </div>
        <div class="panel-body">
            @if($dayOrders->isEmpty())
                <p class="empty-note">No orders for this day yet.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Order</th><th>Time</th><th>Cashier</th><th>Items</th><th>Payment</th><th class="num">Total</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($dayOrders as $o)
                                <tr class="{{ $o->status !== 'completed' ? 'low' : '' }}">
                                    <td class="mono">#{{ $o->order_no }}</td>
                                    <td>{{ \Carbon\Carbon::parse($o->created_at)->format('g:i A') }}</td>
                                    <td>{{ $o->cashier }}</td>
                                    <td>{{ $o->items->sum('quantity') }}</td>
                                    <td>{{ $pay($o->payment_method) }}</td>
                                    <td class="num">{{ $peso($o->total) }}</td>
                                    <td><x-staff.badge :tone="$tone($o->status)">{{ ucfirst($o->status) }}</x-staff.badge></td>
                                    <td class="ta-r"><button type="button" class="btn btn--ghost btn--sm" data-open-modal="receipt-{{ $o->id }}">Receipt</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</main>

{{-- One receipt per order, with the real add-on prices. Outside <main> so printing shows only the open one. --}}
@foreach($dayOrders as $o)
    <x-staff.modal :id="'receipt-' . $o->id">
        <h2 id="receipt-{{ $o->id }}-title">Receipt #{{ $o->order_no }}</h2>

        <div class="receipt">
            <div class="receipt-top">
                <p class="receipt-brand">kape&#8209;nated</p>
                <p class="receipt-addr">Mabini, Davao de Oro &middot; 0917 000 0000</p>
            </div>

            <dl class="receipt-meta">
                <div><dt>Order no.</dt><dd>{{ $o->order_no }}</dd></div>
                <div><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($o->created_at)->format('Y-m-d H:i') }}</dd></div>
                <div><dt>Branch</dt><dd>{{ $branch }}</dd></div>
                <div><dt>Cashier</dt><dd>{{ $o->cashier }}</dd></div>
                <div><dt>Payment</dt><dd>{{ $pay($o->payment_method) }}</dd></div>
                <div><dt>Status</dt><dd>{{ ucfirst($o->status) }}</dd></div>
            </dl>

            <table class="receipt-lines">
                @foreach($o->items as $line)
                    <tr>
                        <td class="rl-qty">{{ $line->quantity }}&times;</td>
                        <td class="rl-name">
                            {{ $line->name }}
                            <span class="rl-opt">{{ ucfirst($line->temperature ?? '') }}@if($line->addons->isNotEmpty()), {{ $line->addons->pluck('name')->join(', ') }}@endif</span>
                        </td>
                        <td class="rl-amt">{{ number_format($line->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </table>

            <div class="receipt-sums">
                <p><span>Subtotal</span><span>{{ $peso($o->total) }}</span></p>
                <p><span>VAT included (12%)</span><span>{{ $peso(round($o->total * 0.12, 2)) }}</span></p>
                <p class="receipt-total"><span>Total</span><span>{{ $peso($o->total) }}</span></p>
            </div>

            <p class="receipt-foot">Salamat! Balik kayo.</p>
        </div>

        <button type="button" class="btn no-print" onclick="window.print()">Print receipt</button>
    </x-staff.modal>
@endforeach

@push('scripts')
<script>
    // Opens a modal by id. (Staff.js already handles closing: ✕, backdrop click, Esc.)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-open-modal]');
        if (btn) document.getElementById(btn.dataset.openModal)?.classList.add('open');
    });
</script>
@endpush

</x-staff.shell>