<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Branch;
use App\Models\DrinkType;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        $orders      =$this->getFormattedOrders();
        $ingredients =$this->getFormattedIngredients();
        $sales       =$this->getSalesByDay();
        $topItems    =$this->getTopItems();

        return view('layouts.admin.Dashboard', compact('orders', 'ingredients', 'sales', 'topItems'));
    }

    public function receipts()
    {
        $orders   = $this->getFormattedOrders();$branches = Branch::pluck('name')->toArray();

        return view('layouts.admin.Receipts', compact('orders', 'branches'));
    }

    public function sales()
    {
        $sales       = $this->getSalesByDay();
        $topItems    = $this->getTopItems();
        $ingredients = $this->getFormattedIngredientsWithBranches();
        $branches    = Branch::pluck('name')->toArray();
        $orders      = $this->getFormattedOrders();
        $movements   = $this->getFormattedStockMovements();
        $invMetrics  = $this->getInventoryMetrics();

        return view('layouts.admin.Reports', compact(
            'sales', 'topItems', 'ingredients', 'branches', 'orders', 'movements', 'invMetrics'
        ));
    }

    public function menu()
    {
        $menuItems = MenuItem::with(['ingredients', 'drinkType'])->get()->map(function ($item) {$recipe = [];
            foreach ($item->ingredients as $ing) {$recipe[$ing->key] = (float)$ing->pivot->quantity;
            }

            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'price'           => (float) $item->price,
                'drink_type_id'   => $item->drink_type_id,
                'drink_type_name' => $item->drinkType->name ?? null,
                'is_active'       => (bool) $item->is_active,
                'image_path'      => $item->image_path,
                'recipe'          => $recipe,
            ];
        })->toArray();

        $drinkTypes  = DrinkType::select('id', 'name')->get()->toArray();$ingredients = $this->getFormattedIngredients();$addons      = Addon::select('id', 'name', 'price')->get()->toArray();

        return view('layouts.admin.Menu', compact('menuItems', 'drinkTypes', 'ingredients', 'addons'));
    }

    public function inventory()
    {
        $ingredients   =$this->getFormattedIngredientsWithBranches();
        $branchWeights =$this->getBranchWeights();
        $recipes       =$this->getRecipes();
        $addonRecipes  =$this->getAddonRecipes();

        return view('layouts.admin.Inventory-admin', compact('ingredients', 'branchWeights', 'recipes', 'addonRecipes'));
    }
    
    private function getInventoryMetrics(): array
    {
        $today = Carbon::today();

        // 1. Stock Received Today
        $stockReceivedCost = DB::table('stock_movements')
            ->join('ingredients', 'stock_movements.ingredient_id', '=', 'ingredients.id')
            ->whereDate('stock_movements.created_at', $today)
            ->where('stock_movements.change', '>', 0)
            ->where('stock_movements.reason', '!=', 'order')
            ->sum(DB::raw('stock_movements.change * ingredients.cost'));

        // 2. Waste Cost Today
        $wasteCost = DB::table('stock_movements')
            ->join('ingredients', 'stock_movements.ingredient_id', '=', 'ingredients.id')
            ->whereDate('stock_movements.created_at', $today)
            ->where('stock_movements.change', '<', 0)
            ->where('stock_movements.reason', '!=', 'order')
            ->sum(DB::raw('ABS(stock_movements.change) * ingredients.cost'));

        // 3. Total Closing Stock Value
        $closingValue = DB::table('branch_inventory')
            ->join('ingredients', 'branch_inventory.ingredient_id', '=', 'ingredients.id')
            ->sum(DB::raw('branch_inventory.stock * ingredients.cost'));

        return [
            'stock_received_cost' => (float) $stockReceivedCost,
            'waste_cost'          => (float) $wasteCost,
            'closing_stock_value' => (float) $closingValue,
        ];
    }

    /* =========================================================================
     | AJAX PERSISTENCE
     | ========================================================================= */

    public function storeDrink(Request $request)
    {
        $validated =$request->validate([
            'id'            => 'nullable|exists:menu_items,id',
            'name'          => 'required|string|max:255',
            'price'         => 'required|numeric|min:0',
            'drink_type_id' => 'nullable|exists:drink_types,id',
            'recipe'        => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $request) {$menuItem = MenuItem::updateOrCreate(
                ['id' => $validated['id'] ?? null],
                [
                    'name'          => $validated['name'],
                    'price'         => $validated['price'],
                    'drink_type_id' => $validated['drink_type_id'] ?? null,
                    'is_active'     => true,
                ]
            );

            if ($request->hasFile('image')) {
                $path =$request->file('image')->store('drinks', 'public');
                $menuItem->image_path = '/storage/' .$path;
                $menuItem->save();
            }

            if (isset($validated['recipe'])) {$syncData = [];
                foreach ($validated['recipe'] as $key =>$qty) {
                    $ing = Ingredient::where('key',$key)->first();
                    if ($ing) {$syncData[$ing->id] = ['quantity' =>$qty];
                    }
                }
                $menuItem->ingredients()->sync($syncData);
            }

            return response()->json([
                'success' => true,
                'item'    => [
                    'id'              => $menuItem->id,
                    'name'            => $menuItem->name,
                    'price'           => (float) $menuItem->price,
                    'drink_type_id'   => $menuItem->drink_type_id,
                    'drink_type_name' => $menuItem->drinkType->name ?? null,
                    'is_active'       => (bool) $menuItem->is_active,
                    'image_path'      => $menuItem->image_path,
                    'recipe'          => $validated['recipe'] ?? [],
                ]
            ]);
        });
    }

    public function toggleDrink(MenuItem $menuItem)
    {
        $menuItem->is_active = !$menuItem->is_active;
        $menuItem->save();

        return response()->json(['success' => true, 'is_active' => $menuItem->is_active]);
    }

    public function storeIngredient(Request $request)
    {
        $validated =$request->validate([
            'name'          => 'required|string|max:255',
            'unit'          => 'required|string|max:20',
            'reorder_level' => 'nullable|numeric|min:0',
            'cost'          => 'nullable|numeric|min:0',
        ]);

        $key = Str::slug($validated['name'], '_');

        $ingredient = Ingredient::create([
            'key'           => $key,
            'name'          => $validated['name'],
            'unit'          => $validated['unit'],
            'reorder_level' => $validated['reorder_level'] ?? 0,             'cost'          =>$validated['cost'] ?? 0,
            'is_active'     => true,
        ]);

        $branches = Branch::all();
        foreach ($branches as$branch) {
            DB::table('branch_inventory')->insert([
                'branch_id'     => $branch->id,
                'ingredient_id' => $ingredient->id,
                'stock'         => 0,
                'reorder_level' => $validated['reorder_level'] ?? 0,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return response()->json([
            'success'    => true,
            'ingredient' => [
                'id'        => $ingredient->id,
                'key'       => $ingredient->key,
                'name'      => $ingredient->name,
                'unit'      => $ingredient->unit,
                'reorder'   => (float) $ingredient->reorder_level,
                'cost'      => (float) $ingredient->cost,
                'is_active' => true,
            ]
        ]);
    }

    public function toggleIngredient(Ingredient $ingredient)
    {
        $ingredient->is_active = !$ingredient->is_active;
        $ingredient->save();

        return response()->json(['success' => true, 'is_active' => $ingredient->is_active]);
    }

    public function processStockOperation(Request $request)
    {
        $validated =$request->validate([
            'type'        => 'required|in:stock_in,stock_out,transfer',
            'branch'      => 'nullable|string',
            'from_branch' => 'nullable|string',
            'to_branch'   => 'nullable|string',
            'items'       => 'required|array',
            'items.*.key' => 'required|string',
            'items.*.qty' => 'required|numeric|gt:0',
            'reason'      => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $type =$validated['type'];

            if ($type === 'stock_in') {
                $branch = Branch::where('name',$validated['branch'])->firstOrFail();

                foreach ($validated['items'] as$itemData) {
                    $ingredient = Ingredient::where('key',$itemData['key'])->firstOrFail();
                    $qty = (float)$itemData['qty'];

                    DB::table('branch_inventory')
                        ->where('branch_id', $branch->id)
                        ->where('ingredient_id', $ingredient->id)
                        ->increment('stock', $qty);

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'change'        => $qty,
                        'reason'        => 'restock',
                    ]);
                }
            } elseif ($type === 'stock_out') {
                $branch = Branch::where('name',$validated['branch'])->firstOrFail();

                foreach ($validated['items'] as$itemData) {
                    $ingredient = Ingredient::where('key',$itemData['key'])->firstOrFail();
                    $qty = (float)$itemData['qty'];

                    DB::table('branch_inventory')
                        ->where('branch_id', $branch->id)
                        ->where('ingredient_id', $ingredient->id)
                        ->decrement('stock', $qty);

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'change'        => -$qty,
                        'reason'        => $validated['reason'] ?? 'waste',
                    ]);
                }
            } elseif ($type === 'transfer') {
                $fromBranch = Branch::where('name',$validated['from_branch'])->firstOrFail();
                $toBranch   = Branch::where('name',$validated['to_branch'])->firstOrFail();

                foreach ($validated['items'] as$itemData) {
                    $ingredient = Ingredient::where('key',$itemData['key'])->firstOrFail();
                    $qty = (float)$itemData['qty'];

                    DB::table('branch_inventory')
                        ->where('branch_id', $fromBranch->id)
                        ->where('ingredient_id', $ingredient->id)
                        ->decrement('stock', $qty);

                    DB::table('branch_inventory')
                        ->where('branch_id', $toBranch->id)
                        ->where('ingredient_id', $ingredient->id)
                        ->increment('stock', $qty);

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'change'        => -$qty,
                        'reason'        => 'correction',
                    ]);

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'change'        => $qty,
                        'reason'        => 'correction',
                    ]);
                }
            }

            return response()->json(['success' => true]);
        });
    }

    /* =========================================================================
     | PRIVATE DATA FETCHERS
     | ========================================================================= */

    private function getFormattedIngredientsWithBranches(): array
    {
        $today = Carbon::today();

        $usedTodayMap = DB::table('stock_movements')
            ->join('orders', 'stock_movements.order_id', '=', 'orders.id')
            ->whereDate('stock_movements.created_at', $today)
            ->where('stock_movements.change', '<', 0)
            ->select('orders.branch_id', 'stock_movements.ingredient_id', DB::raw('SUM(ABS(stock_movements.change)) as total_used'))
            ->groupBy('orders.branch_id', 'stock_movements.ingredient_id')
            ->get()
            ->groupBy('branch_id');

        $branchInventory = DB::table('branch_inventory')
            ->join('branches', 'branch_inventory.branch_id', '=', 'branches.id')
            ->join('ingredients', 'branch_inventory.ingredient_id', '=', 'ingredients.id')
            ->select(
                'branch_inventory.id as inventory_id',
                'branches.id as branch_id',
                'branches.name as branch_name',
                'ingredients.id as ingredient_id',
                'ingredients.key',
                'ingredients.name as ingredient_name',
                'ingredients.unit',
                'ingredients.reorder_level',
                'ingredients.cost',
                'ingredients.is_active',
                'branch_inventory.stock'
            )
            ->get();

        return $branchInventory->map(function ($row) use ($usedTodayMap) {
            $branchUsed =$usedTodayMap->get($row->branch_id)?->where('ingredient_id',$row->ingredient_id)->first()?->total_used ?? 0;

            return [
                'id'          => $row->ingredient_id,
                'branch_id'   => $row->branch_id,
                'branch_name' => $row->branch_name,
                'key'         => $row->key,
                'name'        => $row->ingredient_name,
                'unit'        => $row->unit,
                'stock'       => (float) $row->stock,
                'reorder'     => (float) $row->reorder_level,
                'cost'        => (float) $row->cost,
                'is_active'   => (bool) $row->is_active,
                'used_today'  => (float) $branchUsed,
            ];
        })->toArray();
    }

    private function getFormattedOrders(): array
    {
        return Order::with(['staff', 'branch', 'items.menuItem', 'items.addons'])
            ->latest()
            ->get()
            ->map(function ($order) {
                $total =$order->items->sum(function ($item) {$itemTotal = $item->unit_price * $item->quantity;
                    $addonTotal = $item->addons->sum('pivot.unit_price') *$item->quantity;
                    return $itemTotal +$addonTotal;
                });

                return [
                    'no'      => $order->order_no,
                    'date'    => $order->created_at->format('Y-m-d H:i'),
                    'staff'   => $order->staff->name ?? 'Unknown',
                    'branch'  => $order->branch->name ?? 'Unassigned',
                    'payment' => ucfirst($order->payment_method),
                    'status'  => ucfirst($order->status),
                    'total'   => (float) $total,
                    'items'   => $order->items->map(function ($item) {
                        return [
                            'name'   => $item->menuItem->name ?? 'Custom Item',
                            'qty'    => $item->quantity,
                            'price'  => (float) $item->unit_price,
                            'temp'   => ucfirst($item->temperature ?? 'Cold'),
                            'addons' => $item->addons->pluck('name')->toArray(),
                        ];
                    })->toArray(),
                ];
            })->toArray();
    }

    private function getFormattedIngredients(): array
    {
        $today = Carbon::today();

        $usedTodayMap = DB::table('stock_movements')
            ->whereDate('created_at', $today)
            ->where('change', '<', 0)
            ->select('ingredient_id', DB::raw('SUM(ABS(`change`)) as total_used'))
            ->groupBy('ingredient_id')
            ->pluck('total_used', 'ingredient_id');

        $branchStockTotals = DB::table('branch_inventory')
            ->select('ingredient_id', DB::raw('SUM(stock) as total_stock'))
            ->groupBy('ingredient_id')
            ->pluck('total_stock', 'ingredient_id');

        return Ingredient::all()->map(function ($ing) use ($usedTodayMap,$branchStockTotals) {
            return [
                'id'         => $ing->id,
                'key'        => $ing->key,
                'name'       => $ing->name,
                'unit'       => $ing->unit,
                'stock'      => (float) ($branchStockTotals[$ing->id] ?? 0),                 'reorder'    => (float)$ing->reorder_level,
                'used_today' => (float) ($usedTodayMap[$ing->id] ?? 0),                 'cost'       => (float)$ing->cost,
                'is_active'  => (bool) $ing->is_active,
            ];
        })->toArray();
    }

    private function getFormattedStockMovements(): array
    {
        return StockMovement::with(['ingredient', 'order'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'ingredient' => $m->ingredient->name ?? 'Unknown',
                'change'     => (float) $m->change,
                'reason'     => ucfirst($m->reason ?? 'movement'),
                'date'       => $m->created_at->format('Y-m-d H:i'),
            ])->toArray();
    }

    private function getSalesByDay(): array
    {
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $days->push(Carbon::today()->subDays($i));
        }

        return $days->map(function ($date) {
            $formattedDate =$date->format('Y-m-d');

            $cashOrders = Order::with(['items.addons'])
                ->whereDate('created_at', $formattedDate)
                ->where('payment_method', 'cash')
                ->where('status', 'completed')
                ->get();

            $gcashOrders = Order::with(['items.addons'])
                ->whereDate('created_at', $formattedDate)
                ->where('payment_method', 'gcash')
                ->where('status', 'completed')
                ->get();

            $calculateTotal = function ($orders) {
                return $orders->sum(function ($order) {
                    return $order->items->sum(function ($item) {$itemSum = $item->unit_price * $item->quantity;
                        $addonSum = $item->addons->sum('pivot.unit_price') *$item->quantity;
                        return $itemSum +$addonSum;
                    });
                });
            };

            return [
                'label' => $date->format('D j'),
                'date'  => $formattedDate,
                'cash'  => (float) $calculateTotal($cashOrders),
                'gcash' => (float) $calculateTotal($gcashOrders),
            ];
        })->toArray();
    }

    private function getTopItems(): array
    {
        return DB::table('order_items')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->select(
                'menu_items.name',
                DB::raw('SUM(order_items.quantity) as sold'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name'    => $item->name,
                'sold'    => (int) $item->sold,
                'revenue' => (float) $item->revenue,
            ])->toArray();
    }

    private function getRecipes(): array
    {
        $recipes = [];
        foreach (MenuItem::with('ingredients')->get() as $item) {$recipe = [];
            foreach ($item->ingredients as $ing) {$recipe[$ing->key] = (float)$ing->pivot->quantity;
            }
            $recipes[$item->name] =$recipe;
        }

        return $recipes;
    }

    private function getAddonRecipes(): array
    {
        $addonRecipes = [];
        foreach (Addon::with('ingredients')->get() as $addon) {$recipe = [];
            foreach ($addon->ingredients as $ing) {$recipe[$ing->key] = (float)$ing->pivot->quantity;
            }
            $addonRecipes[$addon->name] =$recipe;
        }

        return $addonRecipes;
    }

    private function getBranchWeights(): array
    {
        $branches = Branch::all();
        $totalBranches =$branches->count();

        if ($totalBranches === 0) {
            return [];
        }

        $weight = round(1 / $totalBranches, 2);$weights = [];
        foreach ($branches as $branch) {$weights[$branch->name] =$weight;
        }

        return $weights;
    }
}