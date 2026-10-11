@props(['order'])

@php
    $subtotal = 0;
    foreach ($order['items'] as $line) {
        $subtotal += $line['qty'] * ($line['price'] + count($line['addons']) * 20);
    }
    $vat = round($subtotal * 0.12, 2);
@endphp

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
        <p><span>Subtotal</span><span>₱{{ number_format($subtotal, 2) }}</span></p>
        <p><span>VAT included (12%)</span><span>₱{{ number_format($vat, 2) }}</span></p>
        <p class="receipt-total"><span>Total</span><span>₱{{ number_format($subtotal, 2) }}</span></p>
    </div>

    <p class="receipt-foot">Salamat! Balik kayo.</p>
</div>