<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    private const SIZES_DEFAULT = [['grande', 'Grande'], ['venti', 'Venti']];
    private const SIZES_BY_TYPE = ['Cones' => [['small', 'Small cone'], ['giant', 'Giant swirl']]];

    private function sizeSet(?string $type): array
    {
        return self::SIZES_BY_TYPE[$type] ?? self::SIZES_DEFAULT;
    }

    /* ------------------------------ order ------------------------------ */

    public function orders(Request $request)
    {
        $user = $request->user();
        $branchId = $user->branch_id;

        $recipes = DB::table('menu_item_ingredient')->get()->groupBy('menu_item_id');
        
        $ingredients = DB::table('ingredients')
            ->leftJoin('branch_inventory', function ($join) use ($branchId) {
                $join->on('ingredients.id', '=', 'branch_inventory.ingredient_id')
                     ->where('branch_inventory.branch_id', '=', $branchId);
            })
            ->get([
                'ingredients.id',
                'ingredients.name',
                'ingredients.is_active as global_active',
                'branch_inventory.stock',
                'branch_inventory.is_active as branch_active'
            ])
            ->keyBy('id');

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
            ->map(function ($m) use ($recipes, $ingredients) {
                $itemRecipes = $recipes[$m->id] ?? collect();
                
                $recipeList = $itemRecipes->map(fn($r) => [
                    'ingredient_id' => (int) $r->ingredient_id,
                    'stock' => (float) ($ingredients[$r->ingredient_id]->stock ?? 0)
                ])->values()->all();

                $isOutOfStock = collect($recipeList)->contains(function($r) use ($ingredients) {
                    $ing = $ingredients[$r['ingredient_id']] ?? null;
                    if (!$ing) return true;
                    return ($ing->stock <= 0) || !$ing->global_active || ($ing->branch_active === 0);
                });

                return [
                    'id'              => $m->id,
                    'name'            => $m->name,
                    'price'           => (float) $m->price,
                    'price_venti'     => $m->price_venti ? (float) $m->price_venti : null,
                    'has_sizes'       => (bool) $m->has_sizes,
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
                    'is_out_of_stock' => $isOutOfStock,
                ];
            });

        $addonTypeMap = DB::table('addon_drink_type')->get()->groupBy('addon_id');
        $addonRecipes = DB::table('addon_ingredient')->get()->groupBy('addon_id');

        $addons = DB::table('addons')
            ->orderBy('id')
            ->get(['id', 'name', 'price'])
            ->map(function ($a) use ($addonTypeMap, $addonRecipes, $ingredients) {
                $aRecipes = $addonRecipes[$a->id] ?? collect();
                
                $isAddonDisabled = $aRecipes->contains(function ($r) use ($ingredients) {
                    $ing = $ingredients[$r->ingredient_id] ?? null;
                    if (!$ing) return true;
                    return ($ing->stock <= 0) || !$ing->global_active || ($ing->branch_active === 0);
                });

                return [
                    'id'              => $a->id,
                    'name'            => $a->name,
                    'price'           => (float) $a->price,
                    'is_disabled'     => $isAddonDisabled,
                    'drink_type_ids'  => isset($addonTypeMap[$a->id])
                        ? $addonTypeMap[$a->id]->pluck('drink_type_id')->map(fn ($id) => (int) $id)->values()->all()
                        : [],
                ];
            });

        return view('layouts.staff.order', [
            'menu'   => $menu,
            'addons' => $addons,
            'nextNo' => $this->nextOrderNo(),
            'branch' => $this->branchName($request),
        ]);
    }

    private function nextOrderNo(): string
    {
        $maxOrderNo = DB::table('orders')
            ->selectRaw('MAX(CAST(order_no AS UNSIGNED)) as max_no')
            ->value('max_no');

        return str_pad((string) (((int) $maxOrderNo) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'payment'          => 'required|in:cash,gcash',
            'gcash_ref'        => 'required_if:payment,gcash|nullable|string|max:50',
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

        $attempts = 0;
        $maxAttempts = 5;

        do {
            try {
                $result = DB::transaction(function () use ($data, $user) {
                    $menu = DB::table('menu_items')
                        ->leftJoin('drink_types', 'drink_types.id', '=', 'menu_items.drink_type_id')
                        ->whereIn('menu_items.id', array_column($data['items'], 'id'))
                        ->where('menu_items.is_active', true)
                        ->get(['menu_items.*', 'drink_types.name as type_name'])
                        ->keyBy('id');

                    foreach ($data['items'] as $row) {
                        if (! $menu->has($row['id'])) {
                            throw ValidationException::withMessages(['items' => 'One of the items is no longer available. Please refresh the page.']);
                        }
                    }
                    $recipes      = DB::table('menu_item_ingredient')->whereIn('menu_item_id', $menu->keys())->get()->groupBy('menu_item_id');
                    $addons       = DB::table('addons')->get()->keyBy('id');
                    $addonRecipes = DB::table('addon_ingredient')->get()->groupBy('addon_id');
                    $addonTypes   = DB::table('addon_drink_type')->get()->groupBy('addon_id');

                    $total = 0;
                    $lines = [];
                    $need  = [];

                    foreach ($data['items'] as $row) {
                        $item   = $menu[$row['id']];
                        $picked = collect($row['addons'] ?? [])->unique()->map(fn ($id) => $addons[$id]);

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

                    $stock = DB::table('branch_inventory')
                        ->join('ingredients', 'ingredients.id', '=', 'branch_inventory.ingredient_id')
                        ->where('branch_inventory.branch_id', $user->branch_id)
                        ->whereIn('branch_inventory.ingredient_id', array_keys($need))
                        ->lockForUpdate()
                        ->get([
                            'branch_inventory.*',
                            'ingredients.name',
                            'ingredients.unit',
                            'ingredients.is_active as global_active'
                        ])
                        ->keyBy('ingredient_id');

                    foreach ($need as $id => $qty) {
                        $itemStock = $stock[$id] ?? null;

                        if (!$itemStock || !$itemStock->global_active || $itemStock->is_active == 0) {
                            $name = $itemStock->name ?? 'an ingredient';
                            throw ValidationException::withMessages([
                                'items' => "Cannot complete order: '{$name}' has been disabled or deactivated by Admin."
                            ]);
                        }

                        if ((float) $itemStock->stock < $qty) {
                            $name = $itemStock->name;
                            $avail = number_format(max(0, (float) $itemStock->stock), 2);
                            throw ValidationException::withMessages([
                                'items' => "Cannot complete order: Not enough {$name} in stock (Available: {$avail} {$itemStock->unit})."
                            ]);
                        }
                    }

                    $now = now();
                    $orderNo = $this->nextOrderNo();

                    $orderId = DB::table('orders')->insertGetId([
                        'order_no'       => $orderNo,
                        'user_id'        => $user->id,
                        'branch_id'      => $user->branch_id,
                        'payment_method' => $data['payment'],
                        'gcash_ref'      => $data['payment'] === 'gcash' ? ($data['gcash_ref'] ?? null) : null,
                        'status'         => 'completed',
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);

                    foreach ($lines as $l) {
                        $itemId = DB::table('order_items')->insertGetId([
                            'order_id'     => $orderId,
                            'menu_item_id' => $l['item']->id,
                            'quantity'     => $l['qty'],
                            'unit_price'   => $l['price'],
                            'size'         => $l['size'],
                            'temperature'  => $l['temp'],
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ]);

                        foreach ($l['addons'] as $a) {
                            DB::table('order_item_addon')->insert([
                                'order_item_id' => $itemId,
                                'addon_id'      => $a->id,
                                'unit_price'    => $a->price,
                                'created_at'    => $now,
                                'updated_at'    => $now,
                            ]);
                        }
                    }

                    foreach ($need as $id => $qty) {
                        DB::table('branch_inventory')
                            ->where('branch_id', $user->branch_id)
                            ->where('ingredient_id', $id)
                            ->decrement('stock', $qty, ['updated_at' => $now]);

                        DB::table('stock_movements')->insert([
                            'ingredient_id' => $id,
                            'order_id'      => $orderId,
                            'user_id'       => $user->id,
                            'branch_id'     => $user->branch_id,
                            'change'        => -$qty,
                            'reason'        => 'order',
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ]);
                    }

                    return ['order_no' => $orderNo, 'total' => (float) $total];
                });

                return response()->json($result + ['next_no' => $this->nextOrderNo()]);

            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 23000) {
                    $attempts++;
                    if ($attempts >= $maxAttempts) {
                        throw ValidationException::withMessages([
                            'items' => 'Order transaction collision. Please tap Complete Order again.'
                        ]);
                    }
                    usleep(100000);
                } else {
                    throw $e;
                }
            }
        } while ($attempts < $maxAttempts);
    }

    /* ----------------------------- inventory ---------------------------- */

    public function inventory(Request $request)
    {
        $user = $request->user();
        $branchId = $user->branch_id;
        $usedToday = $this->usedToday((int) $branchId);

        $ingredients = DB::table('ingredients')
            ->leftJoin('branch_inventory', function ($join) use ($branchId) {
                $join->on('ingredients.id', '=', 'branch_inventory.ingredient_id')
                     ->where('branch_inventory.branch_id', '=', $branchId);
            })
            ->orderBy('ingredients.name')
            ->get([
                'ingredients.id',
                'ingredients.key',
                'ingredients.name',
                'ingredients.unit',
                'ingredients.purchase_unit',
                'ingredients.conversion_factor',
                'ingredients.cost',
                'ingredients.is_active as global_active',
                'branch_inventory.stock',
                'branch_inventory.reorder_level',
                'branch_inventory.is_active as branch_active',
            ])
            ->map(function ($i) use ($usedToday) {
                $i->stock = (float) ($i->stock ?? 0);
                $i->reorder_level = (float) ($i->reorder_level ?? 0);
                $i->used_today = (float) ($usedToday[$i->id] ?? 0);
                $i->is_disabled = !$i->global_active || ($i->branch_active === 0);
                return $i;
            });

        return view('layouts.staff.Inventory-staff', [
            'ingredients' => $ingredients,
            'branch'      => $this->branchName($request),
        ]);
    }

    public function handleInventoryAction(Request $request)
    {
        $data = $request->validate([
            'action_type'     => 'required|in:stock_in,stock_out',
            'ingredient_id'   => 'required|exists:ingredients,id',
            'quantity'        => 'required|numeric|min:0.01|max:100000',
            'unit_multiplier' => 'nullable|numeric',
            'reason'          => 'nullable|in:spoilage,spill,damage,audit',
        ]);

        $user = $request->user();
        if (! $user->branch_id) {
            throw ValidationException::withMessages(['quantity' => 'Your account has no branch assigned. Ask an admin to set one.']);
        }

        $branchId   = (int) $user->branch_id;
        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $inputQty   = (float) $data['quantity'];
        $isOut      = $data['action_type'] === 'stock_out';

        // The browser sends the unit multiplier, so never trust it: it must be one of the
        // unit sizes the form offers for this ingredient's base unit (see Staff.js).
        $multiplier = 1.0;
        if (! $isOut) {
            $allowed = match ($ingredient->unit) {
                'ml'    => [1, 1000, 3785],
                'g'     => [1, 500, 1000],
                default => [1, 50, 100],
            };
            $multiplier = (float) ($data['unit_multiplier'] ?? 1);
            if (! in_array($multiplier, array_map('floatval', $allowed), true)) {
                throw ValidationException::withMessages(['quantity' => 'Invalid unit selected for this ingredient.']);
            }
        }

        $change = $isOut ? -$inputQty : $inputQty * $multiplier;

        DB::transaction(function () use ($ingredient, $branchId, $user, $isOut, $inputQty, $change, $data) {
            // A branch may not have a row for this ingredient yet; create it so stock-in is never silently lost.
            DB::table('branch_inventory')->insertOrIgnore([
                'branch_id'     => $branchId,
                'ingredient_id' => $ingredient->id,
                'stock'         => 0,
                'reorder_level' => 0,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Lock the row so two simultaneous stock-outs can't both pass the check.
            $row = DB::table('branch_inventory')
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredient->id)
                ->lockForUpdate()
                ->first();

            $current = (float) $row->stock;

            if ($isOut) {
                if ($current <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => "Cannot perform stock-out: '{$ingredient->name}' is already out of stock (0 {$ingredient->unit}).",
                    ]);
                }
                if ($inputQty > $current) {
                    $avail = number_format($current, 2);
                    throw ValidationException::withMessages([
                        'quantity' => "Cannot deduct {$inputQty} {$ingredient->unit}. Only {$avail} {$ingredient->unit} currently available.",
                    ]);
                }
            }

            DB::table('branch_inventory')
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredient->id)
                ->update(['stock' => $current + $change, 'updated_at' => now()]);

            DB::table('stock_movements')->insert([
                'ingredient_id' => $ingredient->id,
                'user_id'       => $user->id,
                'branch_id'     => $branchId,
                'change'        => $change,
                'reason'        => $isOut ? 'stock_out: ' . ($data['reason'] ?? 'spoilage') : 'restock',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });

        $amount = rtrim(rtrim(number_format(abs($change), 2, '.', ''), '0'), '.');
        $msg = $isOut
            ? "Deducted {$amount} {$ingredient->unit} from {$ingredient->name}."
            : "Added {$amount} {$ingredient->unit} to {$ingredient->name}.";

        // Staff.js reads result.message from JSON; the old back()->with('status') made it fall back to a generic text.
        return response()->json(['message' => $msg]);
    }

    private function usedToday(int $branchId)
    {
        // Only this branch's usage today. `change` is a reserved word in MySQL, hence the backticks.
        return DB::table('stock_movements')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->where('change', '<', 0)
            ->selectRaw('ingredient_id, ABS(SUM(`change`)) as used')
            ->groupBy('ingredient_id')
            ->pluck('used', 'ingredient_id');
    }

    private function branchName(Request $request): ?string
    {
        return DB::table('branches')->where('id', $request->user()->branch_id)->value('name');
    }

/* ------------------------------ receipts / history ------------------------------ */

public function receipts(Request $request)
{
    $user = $request->user();
    $branchId = $user->branch_id;

    $branchName = DB::table('branches')->where('id', $branchId)->value('name') ?? 'Your Branch';

    // STRICT USER & BRANCH ISOLATION: 
    // Filter specifically by the logged-in staff user's ID
    $rawOrders = DB::table('orders')
        ->join('users', 'users.id', '=', 'orders.user_id')
        ->leftJoin('branches', 'branches.id', '=', 'orders.branch_id')
        ->where('orders.user_id', $user->id) // Strictly restrict to THIS staff user's transactions
        ->select([
            'orders.id',
            'orders.order_no',
            'orders.payment_method',
            'orders.status',
            'orders.created_at',
            'users.name as cashier_name',
            'branches.name as branch_name',
        ])
        ->orderByDesc('orders.created_at') // Newest / Most recent first
        ->orderByDesc('orders.id')
        ->limit(300)
        ->get();

    $orderIds = $rawOrders->pluck('id')->toArray();

    if (empty($orderIds)) {
        return view('layouts.staff.Receipt', [
            'orders' => [],
            'branch' => $branchName,
        ]);
    }

    $items = DB::table('order_items')
        ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
        ->whereIn('order_items.order_id', $orderIds)
        ->select([
            'order_items.id',
            'order_items.order_id',
            'order_items.quantity',
            'order_items.unit_price',
            'order_items.temperature',
            'order_items.size',
            'menu_items.name as item_name',
        ])
        ->get()
        ->groupBy('order_id');

    $itemIds = $items->flatten()->pluck('id')->toArray();

    $addons = DB::table('order_item_addon')
        ->leftJoin('addons', 'addons.id', '=', 'order_item_addon.addon_id')
        ->whereIn('order_item_addon.order_item_id', $itemIds)
        ->select([
            'order_item_addon.order_item_id',
            'order_item_addon.unit_price',
            'addons.name as addon_name',
        ])
        ->get()
        ->groupBy('order_item_id');

    $orders = $rawOrders->map(function ($order) use ($items, $addons, $user, $branchName) {
        $orderItems = $items->get($order->id, collect());
        $total = 0;

        $formattedItems = $orderItems->map(function ($item) use ($addons, &$total) {
            $itemAddons = $addons->get($item->id, collect());
            $addonSum = $itemAddons->sum('unit_price');
            $lineTotal = ($item->unit_price + $addonSum) * $item->quantity;
            $total += $lineTotal;

            return [
                'name'   => $item->item_name ?? 'Custom Item',
                'qty'    => (int) $item->quantity,
                'price'  => (float) $item->unit_price,
                'temp'   => $item->temperature ? ucfirst($item->temperature) : null,
                'sizeLabel' => $item->size ? ucfirst($item->size) : null,
                'addons' => $itemAddons->pluck('addon_name')->toArray(),
            ];
        })->toArray();

        return [
            'no'      => $order->order_no,
            'date'    => Carbon::parse($order->created_at)->format('Y-m-d H:i'),
            'staff'   => $order->cashier_name ?? $user->name ?? 'Staff User',
            'branch'  => $order->branch_name ?? $branchName,
            'payment' => ucfirst($order->payment_method),
            'status'  => ucfirst($order->status),
            'total'   => (float) $total,
            'items'   => $formattedItems,
        ];
    })->toArray();

    return view('layouts.staff.Receipt', [
        'orders' => $orders,
        'branch' => $branchName,
    ]);
}}