<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Branch;
use App\Models\DrinkType;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Http\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function dashboard()
    {
        $orders =$this->getFormattedOrders();
        $ingredients =$this->getFormattedIngredients();
        $sales =$this->getSalesByDay();
        $topItems =$this->getTopItems();

        return view('layouts.admin.Dashboard', compact('orders', 'ingredients', 'sales', 'topItems'));
    }

    /**
     * Display transaction history / receipts.
     */
    public function receipts()
    {
        $orders = $this->getFormattedOrders();$branches = Branch::pluck('name')->toArray();

        return view('layouts.admin.Receipts', compact('orders', 'branches'));
    }

    /**
     * Display sales overview and analytics.
     */
    public function sales()
    {
        $sales =$this->getSalesByDay();
        $topItems =$this->getTopItems();
        $orders =$this->getFormattedOrders();

        return view('layouts.admin.Sales', compact('sales', 'topItems', 'orders'));
    }

    /**
     * Display drink menu management.
     */
    public function menu()
    {
        $menuItems = MenuItem::with('ingredients')->get()->map(function ($item) {
            $recipe = [];
            foreach ($item->ingredients as $ing) {
                $recipe[$ing->key] = (float) $ing->pivot->quantity;
            }

            return [
                'name'            => $item->name,
                'price'           => (float) $item->price,
                'drink_type_id'   => $item->drink_type_id,
                'drink_type_name' => $item->drinkType->name ?? null,
                'is_active'       => $item->is_active,
                'image_path'      => $item->image_path,
                'recipe'          => $recipe,
            ];
        })->toArray();

        $drinkTypes  = DrinkType::select('id', 'name')->get()->toArray();
        $ingredients = $this->getFormattedIngredients();
        
        // Add this line to fetch addons
        $addons = Addon::select('id', 'name', 'price')->get()->toArray();

        // Add 'addons' to compact()
        return view('layouts.admin.Menu', compact('menuItems', 'drinkTypes', 'ingredients', 'addons'));
    }

    /**
     * Display ingredient inventory and stock control.
     */
    public function inventory()
    {
        $ingredients =$this->getFormattedIngredients();
        $branchWeights =$this->getBranchWeights();
        $recipes =$this->getRecipes();
        $addonRecipes =$this->getAddonRecipes();

        return view('layouts.admin.Inventory-admin', compact('ingredients', 'branchWeights', 'recipes', 'addonRecipes'));
    }

    /* =========================================================================
     | PRIVATE DATA TRANSFORMERS (Replaces DemoData)
     | ========================================================================= */

    private function getFormattedOrders(): array
    {
        return Order::with(['staff', 'branch', 'items.menuItem', 'items.addons'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'no'      => $order->order_no,
                    'date'    => $order->created_at->format('Y-m-d H:i'),
                    'staff'   => $order->staff->name ?? 'Unknown',
                    'branch'  => $order->branch->name ?? 'Unassigned',
                    'payment' => ucfirst($order->payment_method),
                    'status'  => ucfirst($order->status),
                    'total'   => (float) $order->total, // <--- Add this line
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

    // In AdminController.php -> getFormattedIngredients()
private function getFormattedIngredients(): array
{
    $today = Carbon::today();

    // 1. Calculate today's usage from Stock Movements
    $usedTodayMap = StockMovement::whereDate('created_at',$today)
        ->where('change', '<', 0)
        ->select('ingredient_id', DB::raw('SUM(ABS(`change`)) as total_used'))
        ->groupBy('ingredient_id')
        ->pluck('total_used', 'ingredient_id');

    // 2. Sum stock from the branch_inventory junction table per ingredient
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
            // Uses real summed branch stock from DB, falls back to $ing->stock if empty
            'stock'      => (float) ($branchStockTotals[$ing->id] ?? $ing->stock),
            'reorder'    => (float) $ing->reorder_level,
            'used_today' => (float) ($usedTodayMap[$ing->id] ?? 0),             'cost'       => (float)$ing->cost,
            'is_active'  => (bool) $ing->is_active,
        ];
    })->toArray();
}

    /**
     * Toggle ingredient active status via AJAX
     */
    public function toggleIngredient(Ingredient $ingredient)
    {
        $ingredient->is_active = !$ingredient->is_active;
        $ingredient->save();

        return response()->json([
            'success'   => true,
            'is_active' => $ingredient->is_active,
        ]);
    }
    
    private function getSalesByDay(): array
    {
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $days->push(Carbon::today()->subDays($i));
        }

        return $days->map(function ($date) {
            $formattedDate =$date->format('Y-m-d');
            
            $cash = Order::whereDate('created_at',$formattedDate)
                ->where('payment_method', 'cash')
                ->where('status', 'completed')
                ->get()
                ->sum(fn ($o) =>$o->total);

            $gcash = Order::whereDate('created_at',$formattedDate)
                ->where('payment_method', 'gcash')
                ->where('status', 'completed')
                ->get()
                ->sum(fn ($o) =>$o->total);

            return [
                'label' => $date->format('D j'),
                'date'  => $formattedDate,
                'cash'  => $cash,
                'gcash' => $gcash,
            ];
        })->toArray();
    }

    private function getTopItems(): array
    {
        return DB::table('order_items')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(7))
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

    public function storeStockIn(Request $request)
    {
        $ingredient = Ingredient::findOrFail($request->ingredient_id);
        
        // Quantity added in purchase unit (e.g. 2 Gallons)
        $inputQty = (float) $request->quantity; 
        
        // Automatically convert to base stock unit (e.g. 2 * 3785.41 ml)
        $convertedBaseStock = $inputQty * $ingredient->conversion_factor;

        $ingredient->increment('stock', $convertedBaseStock);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'change'        => $convertedBaseStock,
            'type'          => 'restock',
            'note'          => "Restocked {$inputQty} {$ingredient->purchase_unit}(s)",
        ]);

        return back()->with('success', 'Stock updated successfully.');
    }
}