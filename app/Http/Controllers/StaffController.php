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

        $addons = DB::table('addons')
            ->orderBy('id')
            ->get(['id', 'name', 'price'])
            ->map(fn ($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'price'          => (float) $a->price,
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
            $menu         = DB::table('menu_items')
                ->leftJoin('drink_types', 'drink_types.id', '=', 'menu_items.drink_type_id')
                ->whereIn('menu_items.id', array_column($data['items'], 'id'))
                ->get(['menu_items.*', 'drink_types.name as type_name'])
                ->keyBy('id');
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
                ->get(['branch_inventory.*', 'ingredients.name', 'ingredients.unit'])
                ->keyBy('ingredient_id');

            foreach ($need as $id => $qty) {
                if (!isset($stock[$id]) || (float) $stock[$id]->stock < $qty) {
                    $name = $stock[$id]->name ?? 'an ingredient';
                    $avail = number_format(max(0, (float) ($stock[$id]->stock ?? 0)), 2);
                    throw ValidationException::withMessages(['items' => "Cannot complete order: Not enough {$name} in stock (Available: {$avail} {$stock[$id]->unit})."]);
                }
            }

            $now = now();
            $orderNo = $this->nextOrderNo();

            $orderId = DB::table('orders')->insertGetId([
                'order_no'       => $orderNo,
                'user_id'        => $user->id,
                'branch_id'      => $user->branch_id,
                'payment_method' => $data['payment'],
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
                    'change'        => -$qty,
                    'reason'        => 'order',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            return ['order_no' => $orderNo, 'total' => (float) $total];
        });

        return response()->json($result + ['next_no' => $this->nextOrderNo()]);
    }

    /* ----------------------------- inventory ---------------------------- */

    public function inventory(Request $request)
    {
        $user = $request->user();
        $branchId = $user->branch_id;
        $usedToday = $this->usedToday();

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
            'unit_multiplier' => 'nullable|numeric|min:0.0001',
            'reason'          => 'nullable|string',
        ]);

        $user = $request->user();
        $branchId = $user->branch_id;
        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $inputQty = (float) $data['quantity'];
        $isOut = $data['action_type'] === 'stock_out';

        $branchInv = DB::table('branch_inventory')
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $currentStock = (float) ($branchInv->stock ?? 0);

        if ($isOut) {
            if ($currentStock <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot perform stock-out: '{$ingredient->name}' is already out of stock (0 {$ingredient->unit})."
                ]);
            }

            if ($inputQty > $currentStock) {
                $avail = number_format($currentStock, 2);
                throw ValidationException::withMessages([
                    'quantity' => "Cannot deduct {$inputQty} {$ingredient->unit}. Only {$avail} {$ingredient->unit} currently available."
                ]);
            }
        }

        DB::transaction(function () use ($ingredient, $branchId, $inputQty, $isOut, $data) {
            if ($isOut) {
                DB::table('branch_inventory')
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $ingredient->id)
                    ->decrement('stock', $inputQty);

                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'change'        => -$inputQty,
                    'reason'        => 'stock_out: ' . ($data['reason'] ?? 'spoilage'),
                ]);
            } else {
                $multiplier = (float) ($data['unit_multiplier'] ?? 1.00);
                $convertedBaseStock = $inputQty * $multiplier;
                
                DB::table('branch_inventory')
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $ingredient->id)
                    ->increment('stock', $convertedBaseStock);

                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'change'        => $convertedBaseStock,
                    'reason'        => 'restock',
                ]);
            }
        });

        $msg = $isOut 
            ? "Successfully deducted {$inputQty} {$ingredient->unit} from {$ingredient->name}." 
            : "Successfully restocked {$inputQty} unit(s) of {$ingredient->name}.";

        return back()->with('status', $msg);
    }

    private function usedToday()
    {
        return StockMovement::whereDate('created_at', today())
            ->where('change', '<', 0)
            ->get(['ingredient_id', 'change'])
            ->groupBy('ingredient_id')
            ->map(fn ($rows) => abs($rows->sum('change')));
    }

    private function nextOrderNo(): string
    {
        return str_pad((string) (((int) DB::table('orders')->max('order_no')) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function branchName(Request $request): ?string
    {
        return DB::table('branches')->where('id', $request->user()->branch_id)->value('name');
    }
}