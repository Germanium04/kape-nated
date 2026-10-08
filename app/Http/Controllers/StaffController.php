<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything the staff role can do: take orders and look after stock.
 * Stock pages use the Ingredient / StockMovement models; the order and receipt
 * queries still use the query builder.
 */
class StaffController extends Controller
{
    /**
     * The two sizes a drink can be sold in, by drink type: [value saved on the order, label shown].
     * The first uses menu_items.price, the second uses menu_items.price_venti.
     * Cones are the exception: small cone / giant swirl instead of Grande / Venti.
     */
    private const SIZES_DEFAULT = [['grande', 'Grande'], ['venti', 'Venti']];
    private const SIZES_BY_TYPE = ['Cones' => [['small', 'Small cone'], ['giant', 'Giant swirl']]];

    private function sizeSet(?string $type): array
    {
        return self::SIZES_BY_TYPE[$type] ?? self::SIZES_DEFAULT;
    }

    /* ------------------------------ order ------------------------------ */

    public function orders(Request $request)
    {
        // Fetch Menu Items with attributes & category IDs
        $menu = DB::table('menu_items')
            ->leftJoin('drink_types', 'drink_types.id', '=', 'menu_items.drink_type_id')
            ->where('menu_items.is_active', true)
            ->orderBy('menu_items.id')
            ->get([
                'menu_items.id',
                'menu_items.name',
                'menu_items.price',
                'menu_items.price_venti',
                'menu_items.has_sizes',
                'menu_items.has_temperature',
                'menu_items.drink_type_id',
                'drink_types.name as type',
            ])
            ->map(fn ($m) => [
                'id'              => $m->id,
                'name'            => $m->name,
                'price'           => (float) $m->price,
                'price_venti'     => $m->price_venti ? (float) $m->price_venti : null,
                'has_sizes'       => (bool) $m->has_sizes,
                // [{value,label,price}, ...]  empty when the drink has a single price
                'sizes'           => $m->has_sizes
                    ? collect($this->sizeSet($m->type))->map(fn ($s, $i) => [
                        'value' => $s[0],
                        'label' => $s[1],
                        'price' => (float) ($i === 1 && $m->price_venti !== null ? $m->price_venti : $m->price),
                    ])->values()->all()
                    : [],
                'has_temperature' => (bool) $m->has_temperature,
                'drink_type_id'   => $m->drink_type_id !== null ? (int) $m->drink_type_id : null,
                'type'            => $m->type ?? 'Other',
            ]);

        // Eagerly map Addons to their allowed Drink Type IDs via addon_drink_type pivot
        $addonTypeMap = DB::table('addon_drink_type')
            ->get()
            ->groupBy('addon_id');

        $addons = DB::table('addons')
            ->orderBy('id')
            ->get(['id', 'name', 'price'])
            ->map(fn ($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'price'          => (float) $a->price,
                // Empty list = not restricted, offered on every drink.
                'drink_type_ids' => isset($addonTypeMap[$a->id])
                    ? $addonTypeMap[$a->id]->pluck('drink_type_id')->map(fn ($id) => (int) $id)->values()->all()
                    : [],
            ]);

        return view('layouts.staff.Order', [
            'menu'   => $menu,
            'addons' => $addons,
            'nextNo' => $this->nextOrderNo(),
            'branch' => $this->branchName($request),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'payment'          => 'required|in:cash,gcash',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|exists:menu_items,id',
            'items.*.qty'      => 'required|integer|min:1|max:50',
            'items.*.size'     => 'nullable|in:grande,venti,small,giant',
            'items.*.temp'     => 'nullable|in:hot,cold',
            'items.*.addons'   => 'nullable|array',
            'items.*.addons.*' => 'exists:addons,id',
        ]);

        $user = $request->user();
        if (! $user->branch_id) {
            throw ValidationException::withMessages(['items' => 'Your account has no branch assigned. Ask an admin to set one.']);
        }

        $result = DB::transaction(function () use ($data, $user) {
            // Prices and recipes always come from the database, never from the browser.
            $menu         = DB::table('menu_items')
                ->leftJoin('drink_types', 'drink_types.id', '=', 'menu_items.drink_type_id')
                ->whereIn('menu_items.id', array_column($data['items'], 'id'))
                ->get(['menu_items.*', 'drink_types.name as type_name'])
                ->keyBy('id');
            $recipes      = DB::table('menu_item_ingredient')->whereIn('menu_item_id', $menu->keys())->get()->groupBy('menu_item_id');
            $addons       = DB::table('addons')->get()->keyBy('id');
            $addonRecipes = DB::table('addon_ingredient')->get()->groupBy('addon_id');
            $addonTypes   = DB::table('addon_drink_type')->get()->groupBy('addon_id');   // add-on => drink types it may go on

            $total = 0;
            $lines = [];
            $need  = [];   // ingredient_id => total amount this order uses

            foreach ($data['items'] as $row) {
                $item   = $menu[$row['id']];
                $picked = collect($row['addons'] ?? [])->unique()->map(fn ($id) => $addons[$id]);

                // Only drinks flagged has_sizes carry a size. The second size (Venti / giant swirl) uses price_venti.
                $size  = null;
                $price = $item->price;
                if ($item->has_sizes) {
                    $values = array_column($this->sizeSet($item->type_name), 0);
                    $size   = in_array($row['size'] ?? null, $values, true) ? $row['size'] : $values[0];
                    if ($size === $values[1] && $item->price_venti !== null) {
                        $price = $item->price_venti;
                    }
                }
                $temp  = $item->has_temperature ? ($row['temp'] ?? 'cold') : null;

                // An add-on with a drink-type list may only go on those drinks.
                foreach ($picked as $a) {
                    $only = $addonTypes[$a->id] ?? null;
                    if ($only && ! $only->contains('drink_type_id', $item->drink_type_id)) {
                        throw ValidationException::withMessages(['items' => "{$a->name} can't be added to {$item->name}."]);
                    }
                }

                $total += ($price + $picked->sum('price')) * $row['qty'];

                foreach ($recipes[$item->id] ?? [] as $r) {
                    $need[$r->ingredient_id] = ($need[$r->ingredient_id] ?? 0) + $r->quantity * $row['qty'];
                }
                foreach ($picked as $addon) {
                    foreach ($addonRecipes[$addon->id] ?? [] as $r) {
                        $need[$r->ingredient_id] = ($need[$r->ingredient_id] ?? 0) + $r->quantity * $row['qty'];
                    }
                }

                $lines[] = ['item' => $item, 'qty' => $row['qty'], 'price' => $price, 'size' => $size, 'temp' => $temp, 'addons' => $picked];
            }

            // Lock the stock rows, then make sure everything can actually be made.
            $stock = DB::table('ingredients')->whereIn('id', array_keys($need))->lockForUpdate()->get()->keyBy('id');
            foreach ($need as $id => $qty) {
                if ((float) $stock[$id]->stock < $qty) {
                    throw ValidationException::withMessages(['items' => "Not enough {$stock[$id]->name} in stock for this order."]);
                }
            }

            $now = now();
            $orderNo = $this->nextOrderNo();

            $orderId = DB::table('orders')->insertGetId([
                'order_no' => $orderNo, 'user_id' => $user->id, 'branch_id' => $user->branch_id,
                'payment_method' => $data['payment'], 'status' => 'completed',
                'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($lines as $l) {
                $itemId = DB::table('order_items')->insertGetId([
                    'order_id' => $orderId, 'menu_item_id' => $l['item']->id, 'quantity' => $l['qty'],
                    'unit_price' => $l['price'], 'size' => $l['size'], 'temperature' => $l['temp'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                foreach ($l['addons'] as $a) {
                    DB::table('order_item_addon')->insert([
                        'order_item_id' => $itemId, 'addon_id' => $a->id, 'unit_price' => $a->price,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            foreach ($need as $id => $qty) {
                DB::table('ingredients')->where('id', $id)->decrement('stock', $qty, ['updated_at' => $now]);
                DB::table('stock_movements')->insert([
                    'ingredient_id' => $id, 'order_id' => $orderId, 'change' => -$qty, 'reason' => 'order',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            return ['order_no' => $orderNo, 'total' => (float) $total];
        });

        return response()->json($result + ['next_no' => $this->nextOrderNo()]);
    }

    /* ----------------------------- dashboard ---------------------------- */

    /** One branch's day: sales, payment split, week chart, best sellers, low stock, receipts. */
    public function dashboard(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $user = $request->user();
        $date = Carbon::parse($request->query('date', today()))->startOfDay();
        if ($date->isFuture()) {
            $date = today();
        }

        // 7-day window ending on the chosen date, for THIS branch only.
        $orders = $user->branch_id
            ? $this->branchOrders($user->branch_id, $date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay())
            : collect();

        $on = fn (string $day) => $orders->filter(fn ($o) => str_starts_with($o->created_at, $day));

        $dayOrders = $on($date->toDateString())->values();
        $done      = $dayOrders->where('status', 'completed');
        $sales     = (float) $done->sum('total');
        $count     = $done->count();

        $before = (float) $on($date->copy()->subDay()->toDateString())->where('status', 'completed')->sum('total');
        $delta  = $before > 0 ? (int) round(($sales - $before) / $before * 100) : null;

        $split = [
            'cash'  => (float) $done->where('payment_method', 'cash')->sum('total'),
            'gcash' => (float) $done->where('payment_method', 'gcash')->sum('total'),
        ];

        $week = collect(range(6, 0))->map(function ($back) use ($date, $on) {
            $d    = $date->copy()->subDays($back);
            $rows = $on($d->toDateString())->where('status', 'completed');

            return [
                'label' => $d->format('D j'),
                'cash'  => (float) $rows->where('payment_method', 'cash')->sum('total'),
                'gcash' => (float) $rows->where('payment_method', 'gcash')->sum('total'),
            ];
        });

        $topItems = $orders->where('status', 'completed')
            ->flatMap(fn ($o) => $o->items)
            ->groupBy('name')
            ->map(fn ($rows, $name) => ['name' => $name, 'sold' => (int) $rows->sum('quantity'), 'revenue' => (float) $rows->sum('line_total')])
            ->sortByDesc('sold')->take(5)->values();

        $usedToday = $this->usedToday();
        $lowStock = Ingredient::orderBy('name')->get()
            ->filter(fn (Ingredient $i) => $i->isLow())
            ->map(function (Ingredient $i) use ($usedToday) {
                $used = (float) ($usedToday[$i->id] ?? 0);
                $i->days_left = $used > 0 ? (int) floor($i->stock / $used) : null;
                return $i;
            })->values();

        return view('layouts.staff.Dashboard', [
            'branch'    => $this->branchName($request),
            'date'      => $date,
            'isToday'   => $date->isToday(),
            'dayOrders' => $dayOrders,
            'sales'     => $sales,
            'count'     => $count,
            'avg'       => $count ? $sales / $count : 0,
            'delta'     => $delta,
            'split'     => $split,
            'week'      => $week,
            'topItems'  => $topItems,
            'lowStock'  => $lowStock,
        ]);
    }

    /* ----------------------------- inventory ---------------------------- */

    public function inventory(Request $request)
    {
        $usedToday = $this->usedToday();

        $ingredients = Ingredient::orderBy('name')->get()->each(
            fn (Ingredient $i) => $i->used_today = (float) ($usedToday[$i->id] ?? 0)
        );

        return view('layouts.staff.Inventory-staff', [
            'ingredients' => $ingredients,
            'lowCount'    => $ingredients->filter(fn ($i) => $i->state !== 'ok')->count(),
            'branch'      => $this->branchName($request),
        ]);
    }

    public function restock(Request $request, Ingredient $ingredient)
    {
        $qty = (float) $request->validate(['quantity' => 'required|numeric|min:0.01|max:100000'])['quantity'];

        DB::transaction(function () use ($ingredient, $qty) {
            $ingredient->increment('stock', $qty);
            StockMovement::create(['ingredient_id' => $ingredient->id, 'change' => $qty, 'reason' => 'restock']);
        });

        return back()->with('status', 'Added ' . rtrim(rtrim(number_format($qty, 2), '0'), '.') . " {$ingredient->unit} to {$ingredient->name}.");
    }

    /* ------------------------------ helpers ----------------------------- */

    /** Ingredient usage so far today, ['ingredient_id' => amount]. Summed in PHP so it works on MySQL and SQLite. */
    private function usedToday()
    {
        return StockMovement::whereDate('created_at', today())
            ->where('change', '<', 0)
            ->get(['ingredient_id', 'change'])
            ->groupBy('ingredient_id')
            ->map(fn ($rows) => -$rows->sum('change'));
    }

    /**
     * Orders for one branch in a date range, newest first. Each order carries
     * ->cashier, ->items (each with ->name, ->addons, ->line_total) and ->total,
     * because the orders table stores no total of its own.
     */
    private function branchOrders(int $branchId, Carbon $from, Carbon $to)
    {
        $orders = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.branch_id', $branchId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->orderByDesc('orders.created_at')
            ->get(['orders.*', 'users.name as cashier']);

        $items = DB::table('order_items')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->whereIn('order_items.order_id', $orders->pluck('id'))
            ->get(['order_items.*', 'menu_items.name']);

        $addons = DB::table('order_item_addon')
            ->join('addons', 'addons.id', '=', 'order_item_addon.addon_id')
            ->whereIn('order_item_addon.order_item_id', $items->pluck('id'))
            ->get(['order_item_addon.*', 'addons.name'])
            ->groupBy('order_item_id');

        $items = $items->each(function ($i) use ($addons) {
            $i->addons     = $addons[$i->id] ?? collect();
            $i->line_total = ($i->unit_price + $i->addons->sum('unit_price')) * $i->quantity;
        })->groupBy('order_id');

        return $orders->each(function ($o) use ($items) {
            $o->items = $items[$o->id] ?? collect();
            $o->total = $o->items->sum('line_total');
        });
    }

    private function nextOrderNo(): string
    {
        // order_no is zero-padded ("0148"), so a string max sorts correctly up to 9999.
        return str_pad((string) (((int) DB::table('orders')->max('order_no')) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function branchName(Request $request): ?string
    {
        return DB::table('branches')->where('id', $request->user()->branch_id)->value('name');
    }
}