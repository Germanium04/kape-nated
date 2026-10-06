<x-staff.shell title="Inventory">

    <main class="wrap">

        <div class="stat-row--aligned">
            <x-staff.stat :value="$ingredients->count()" label="Tracked ingredients" />
            <x-staff.stat 
                label="Items to reorder" 
                :value="$lowCount" 
                :trend="$lowCount ? 'Check the stock room' : null" 
                :tone="$lowCount ? 'alert' : 'neutral'" 
            />
        </div>


    <x-staff.page title="Inventory — {{ $branch ?? 'No branch assigned' }}">

        @if(session('status'))
            <p class="flash">{{ session('status') }}</p>
        @endif
        @error('quantity')
            <p class="flash err">{{ $message }}</p>
        @enderror

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th class="num">On hand</th>
                        <th class="num">Reorder at</th>
                        <th class="num">Used today</th>
                        <th>Status</th>
                        <th>Restock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ingredients as $item)
                        @php
                            $trim = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
                            $label = ['ok' => 'Healthy', 'low' => 'Low', 'out' => 'Out of stock'][$item->state];
                        @endphp
                        <tr class="{{ $item->state !== 'ok' ? 'low' : '' }}">
                            <td>{{ $item->name }}</td>
                            <td class="num">{{ $trim($item->stock) }} {{ $item->unit }}</td>
                            <td class="num">{{ $trim($item->reorder_level) }} {{ $item->unit }}</td>
                            <td class="num">{{ $item->used_today > 0 ? $trim($item->used_today).' '.$item->unit : '—' }}</td>
                            <td><x-staff.badge :tone="$item->state">{{ $label }}</x-staff.badge></td>
                            <td>
                                <form method="POST" action="{{ route('staff.inventory.restock', $item->id) }}" class="restock">
                                    @csrf
                                    <input type="number" name="quantity" step="0.01" min="0.01" placeholder="Qty" required>
                                    <button type="submit">Add</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </x-staff.page>
    </main>

</x-staff.shell>