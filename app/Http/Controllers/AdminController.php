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
        $orders      = $this->getFormattedOrders();
        $ingredients = $this->getFormattedIngredients();
        $sales       = $this->getSalesByDay();
        $topItems    = $this->getTopItems();

        return view('layouts.admin.Dashboard', compact('orders', 'ingredients', 'sales', 'topItems'));
    }

    public function receipts()
    {
        $orders   = $this->getFormattedOrders();
        $branches = Branch::pluck('name')->toArray();

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
        $menuItems = MenuItem::with(['ingredients', 'drinkType'])->get()->map(function ($item) {
            $recipe = [];
            foreach ($item->ingredients as $ing) {
                $recipe[$ing->key] = (float)$ing->pivot->quantity;
            }

            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'price'           => (float) $item->price,
                'price_venti'     => $item->price_venti ? (float) $item->price_venti : null,
                'has_sizes'       => (bool) $item->has_sizes,
                'has_temperature' => (bool) $item->has_temperature,
                'drink_type_id'   => $item->drink_type_id,
                'drink_type_name' => $item->drinkType->name ?? null,
                'is_active'       => (bool) $item->is_active,
                'image_path'      => $item->image_path,
                'recipe'          => $recipe,
            ];
        })->toArray();

        $drinkTypes = DrinkType::with('addons')->get()->map(function ($type) {
            return [
                'id'         => $type->id,
                'name'       => $type->name,
                'addon_ids'  => $type->addons->pluck('id')->toArray(),
            ];
        })->toArray();

        $ingredients = $this->getFormattedIngredients();
        $addons      = Addon::select('id', 'name', 'price')->get()->toArray();

        return view('layouts.admin.Menu', compact('menuItems', 'drinkTypes', 'ingredients', 'addons'));
    }

    public function inventory()
    {
        $ingredients   = $this->getFormattedIngredientsWithBranches();
        $branchWeights = $this->getBranchWeights();
        $recipes       = $this->getRecipes();
        $addonRecipes  = $this->getAddonRecipes();
        $movements     = $this->getFormattedStockMovements();

        return view('layouts.admin.Inventory-admin', compact(
            'ingredients', 'branchWeights', 'recipes', 'addonRecipes', 'movements'
        ));
    }
    
    /* =========================================================================
     | REPORT EXPORTS (CSV + server-side PDF)
     | ========================================================================= */

    public function exportCsv(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $data = $this->buildReportExportData($filters);

        $filename = 'kape-report-' . $filters['scope'] . '-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($data, $filters) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Kape-nated Report', ucfirst($filters['scope']), $data['range_label'], $data['branch_label']]);
            fputcsv($out, ['Exported', now()->format('d M Y, h:i A'), 'By', auth()->user()->name ?? 'Admin']);
            fputcsv($out, []);

            if (in_array($filters['scope'], ['sales', 'both'], true)) {
                $this->writeSalesCsv($out, $data);
            }

            if ($filters['scope'] === 'both') {
                fputcsv($out, []);
            }

            if (in_array($filters['scope'], ['inventory', 'both'], true)) {
                $this->writeInventoryCsv($out, $data);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $data = $this->buildReportExportData($filters);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('layouts.admin.ReportPdf', [
            'data'    => $data,
            'filters' => $filters,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('kape-report-' . $filters['scope'] . '-' . now()->format('Ymd-His') . '.pdf');
    }

    private function validateExportFilters(Request $request): array
    {
        $validated = $request->validate([
            'scope'   => 'nullable|in:sales,inventory,both',
            'branch'  => 'nullable|string',
            'view_by' => 'nullable|in:day,month,year',
            'date'    => 'nullable|date',
        ]);

        $branch = $validated['branch'] ?? 'all';
        if ($branch !== 'all' && ! Branch::where('name', $branch)->exists()) {
            $branch = 'all';
        }

        return [
            'scope'   => $validated['scope'] ?? 'both',
            'branch'  => $branch,
            'view_by' => $validated['view_by'] ?? 'day',
            'date'    => $validated['date'] ?? Carbon::today()->format('Y-m-d'),
        ];
    }

    private function exportRangeMatches(string $dateTime, array $filters): bool
    {
        $day = Str::before($dateTime, ' ');
        if ($filters['view_by'] === 'day') {
            return $day === $filters['date'];
        }
        if ($filters['view_by'] === 'month') {
            return substr($day, 0, 7) === substr($filters['date'], 0, 7);
        }
        return substr($day, 0, 4) === substr($filters['date'], 0, 4);
    }

    private function exportMatchesBranch(?string $branchName, array $filters): bool
    {
        if ($filters['branch'] === 'all') {
            return true;
        }
        return strtolower($branchName ?? '') === strtolower($filters['branch']);
    }

    private function getInventoryMetrics(): array
    {
        $today = Carbon::today();

        $stockReceivedCost = DB::table('stock_movements')
            ->join('ingredients', 'stock_movements.ingredient_id', '=', 'ingredients.id')
            ->whereDate('stock_movements.created_at', $today)
            ->where('stock_movements.change', '>', 0)
            ->where('stock_movements.reason', '!=', 'order')
            ->sum(DB::raw('stock_movements.change * ingredients.cost'));

        $wasteCost = DB::table('stock_movements')
            ->join('ingredients', 'stock_movements.ingredient_id', '=', 'ingredients.id')
            ->whereDate('stock_movements.created_at', $today)
            ->where('stock_movements.change', '<', 0)
            ->where('stock_movements.reason', '!=', 'order')
            ->sum(DB::raw('ABS(stock_movements.change) * ingredients.cost'));

        $closingValue = DB::table('branch_inventory')
            ->join('ingredients', 'branch_inventory.ingredient_id', '=', 'ingredients.id')
            ->sum(DB::raw('branch_inventory.stock * ingredients.cost'));

        return [
            'stock_received_cost' => (float) $stockReceivedCost,
            'waste_cost'          => (float) $wasteCost,
            'closing_stock_value' => (float) $closingValue,
        ];
    }

    private function buildReportExportData(array $filters): array
    {
        $filteredOrders = collect($this->getFormattedOrders())
            ->filter(fn ($o) => ($o['status'] ?? '') === 'Completed'
                && $this->exportRangeMatches($o['date'] ?? '', $filters)
                && $this->exportMatchesBranch($o['branch'] ?? null, $filters))
            ->values()
            ->all();

        $gross = collect($filteredOrders)->sum(fn ($o) => $o['total'] ?? 0);
        $cash = collect($filteredOrders)->filter(fn ($o) => strtolower($o['payment'] ?? '') === 'cash')->sum(fn ($o) => $o['total'] ?? 0);
        $gcash = collect($filteredOrders)->filter(fn ($o) => strtolower($o['payment'] ?? '') === 'gcash')->sum(fn ($o) => $o['total'] ?? 0);
        $cups = collect($filteredOrders)->sum(fn ($o) => collect($o['items'] ?? [])->sum('qty'));
        $count = count($filteredOrders);

        $itemMap = [];
        $branchMap = [];
        foreach ($filteredOrders as $o) {
            $branchMap[$o['branch']] = ($branchMap[$o['branch']] ?? 0) + ($o['total'] ?? 0);
            foreach ($o['items'] ?? [] as $item) {
                $name = $item['name'] ?? 'Unknown';
                if (! isset($itemMap[$name])) {
                    $itemMap[$name] = ['name' => $name, 'sold' => 0, 'revenue' => 0];
                }
                $itemMap[$name]['sold'] += $item['qty'] ?? 0;
                $itemMap[$name]['revenue'] += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
            }
        }
        arsort($branchMap);
        usort($itemMap, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $filteredIngredients = collect($this->getFormattedIngredientsWithBranches())
            ->filter(fn ($i) => $this->exportMatchesBranch($i['branch_name'] ?? null, $filters))
            ->values()
            ->all();

        $filteredMovements = collect($this->getFormattedStockMovements())
            ->filter(fn ($m) => $this->exportRangeMatches(($m['date'] ?? '') . ' 00:00', $filters)
                && $this->exportMatchesBranch($m['branch'] ?? null, $filters))
            ->values()
            ->all();

        $rangeDate = Carbon::parse($filters['date']);
        $rangeLabel = $filters['view_by'] === 'day'
            ? $rangeDate->format('d M Y') . ' (DAY)'
            : ($filters['view_by'] === 'month'
                ? $rangeDate->format('M Y') . ' (MONTH)'
                : $rangeDate->format('Y') . ' (YEAR)');

        return [
            'range_label'  => $rangeLabel,
            'branch_label' => $filters['branch'] === 'all' ? 'All Branches' : $filters['branch'],
            'generated_by' => auth()->user()->name ?? 'Admin User',
            'exported_at'  => now()->format('d M Y, h:i A'),
            'summary'      => [
                'gross'      => $gross,
                'orders'     => $count,
                'avg_ticket' => $count ? $gross / $count : 0,
                'cups'       => $cups,
                'cash'       => $cash,
                'gcash'      => $gcash,
            ],
            'by_branch'   => $branchMap,
            'items'       => array_values($itemMap),
            'orders'      => $filteredOrders,
            'ingredients' => $filteredIngredients,
            'movements'   => $filteredMovements,
            'metrics'     => $this->getInventoryMetrics(),
        ];
    }

    private function writeSalesCsv($out, array $data): void
    {
        $s = $data['summary'];

        fputcsv($out, ['=== SALES ===']);
        fputcsv($out, ['Gross sales', number_format($s['gross'], 2, '.', '')]);
        fputcsv($out, ['Orders', $s['orders']]);
        fputcsv($out, ['Average ticket', number_format($s['avg_ticket'], 2, '.', '')]);
        fputcsv($out, ['Cups served', $s['cups']]);
        fputcsv($out, ['Cash', number_format($s['cash'], 2, '.', '')]);
        fputcsv($out, ['GCash', number_format($s['gcash'], 2, '.', '')]);
        fputcsv($out, []);

        fputcsv($out, ['Order', 'Date', 'Branch', 'Cashier', 'Items', 'Payment', 'Status', 'Total']);
        foreach ($data['orders'] as $o) {
            $names = implode('; ', array_map(fn ($i) => ($i['qty'] ?? 0) . 'x ' . ($i['name'] ?? ''), $o['items'] ?? []));
            fputcsv($out, [
                $o['no'] ?? '', $o['date'] ?? '', $o['branch'] ?? '', $o['staff'] ?? '',
                $names, $o['payment'] ?? '', $o['status'] ?? '',
                number_format($o['total'] ?? 0, 2, '.', ''),
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['Drink', 'Cups sold', 'Revenue', 'Share %']);
        foreach ($data['items'] as $item) {
            $share = $s['gross'] ? round(($item['revenue'] / $s['gross']) * 100) : 0;
            fputcsv($out, [$item['name'], $item['sold'], number_format($item['revenue'], 2, '.', ''), $share]);
        }
    }

    private function writeInventoryCsv($out, array $data): void
    {
        fputcsv($out, ['=== INVENTORY ===']);
        fputcsv($out, ['Branch', 'Ingredient', 'Unit', 'On hand', 'Reorder level', 'Used today', 'Status']);
        foreach ($data['ingredients'] as $i) {
            $status = ($i['stock'] ?? 0) <= 0 ? 'Out of stock' : ((($i['stock'] ?? 0) <= ($i['reorder'] ?? 0)) ? 'Low' : 'Healthy');
            fputcsv($out, [
                $i['branch_name'] ?? '', $i['name'] ?? '', $i['unit'] ?? '',
                $i['stock'] ?? 0, $i['reorder'] ?? 0, $i['used_today'] ?? 0, $status,
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['Date', 'Ingredient', 'Branch', 'Reason', 'Change']);
        foreach ($data['movements'] as $m) {
            fputcsv($out, [$m['date'] ?? '', $m['ingredient'] ?? '', $m['branch'] ?? '', $m['reason'] ?? '', $m['change'] ?? 0]);
        }
    }

    /* =========================================================================
     | AJAX PERSISTENCE & TRANSACTION ERROR HANDLING
     | ========================================================================= */

    public function storeDrink(Request $request)
    {
        $validated = $request->validate([
            'id'              => 'nullable|exists:menu_items,id',
            'name'            => 'required|string|max:255',
            'price'           => 'required|numeric|min:0',
            'price_venti'     => 'nullable|numeric|min:0',
            'has_sizes'       => 'nullable|boolean',
            'has_temperature' => 'nullable|boolean',
            'drink_type_id'   => 'nullable|exists:drink_types,id',
            'recipe'          => 'nullable|array',
        ]);

        try {
            return DB::transaction(function () use ($validated, $request) {
                $menuItem = MenuItem::updateOrCreate(
                    ['id' => $validated['id'] ?? null],
                    [
                        'name'            => $validated['name'],
                        'price'           => $validated['price'],
                        'price_venti'     => $validated['price_venti'] ?? null,
                        'has_sizes'       => $request->boolean('has_sizes'),
                        'has_temperature' => $request->boolean('has_temperature'),
                        'drink_type_id'   => $validated['drink_type_id'] ?? null,
                        'is_active'       => true,
                    ]
                );

                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('drinks', 'public');
                    $menuItem->image_path = '/storage/' . $path;
                    $menuItem->save();
                }

                if (isset($validated['recipe'])) {
                    $syncData = [];
                    foreach ($validated['recipe'] as $key => $qty) {
                        $ing = Ingredient::where('key', $key)->first();
                        if ($ing) {
                            $syncData[$ing->id] = ['quantity' => $qty];
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
                        'price_venti'     => $menuItem->price_venti ? (float)$menuItem->price_venti : null,
                        'has_sizes'       => (bool) $menuItem->has_sizes,
                        'has_temperature' => (bool) $menuItem->has_temperature,
                        'drink_type_id'   => $menuItem->drink_type_id,
                        'drink_type_name' => $menuItem->drinkType->name ?? null,
                        'is_active'       => (bool) $menuItem->is_active,
                        'image_path'      => $menuItem->image_path,
                        'recipe'          => $validated['recipe'] ?? [],
                    ]
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction failed while saving drink: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeDrinkType(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255|unique:drink_types,name',
            'addons' => 'nullable|array',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $drinkType = DrinkType::create(['name' => $validated['name']]);

                if (!empty($validated['addons'])) {
                    $drinkType->addons()->sync($validated['addons']);
                }

                return response()->json([
                    'success'    => true,
                    'drink_type' => [
                        'id'        => $drinkType->id,
                        'name'      => $drinkType->name,
                        'addon_ids' => $validated['addons'] ?? [],
                    ]
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction failed while creating category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleDrink(MenuItem $menuItem)
    {
        try {
            $menuItem->is_active = !$menuItem->is_active;
            $menuItem->save();

            return response()->json(['success' => true, 'is_active' => $menuItem->is_active]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeIngredient(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'unit'          => 'required|string|max:20',
            'reorder_level' => 'nullable|numeric|min:0',
            'cost'          => 'nullable|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $key = Str::slug($validated['name'], '_');

                $ingredient = Ingredient::create([
                    'key'           => $key,
                    'name'          => $validated['name'],
                    'unit'          => $validated['unit'],
                    'reorder_level' => $validated['reorder_level'] ?? 0,
                    'cost'          => $validated['cost'] ?? 0,
                    'is_active'     => true,
                ]);

                $branches = Branch::all();
                foreach ($branches as $branch) {
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
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction failed while registering raw ingredient: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleIngredient(Ingredient $ingredient)
    {
        try {
            DB::transaction(function () use ($ingredient) {
                $ingredient->is_active = !$ingredient->is_active;
                $ingredient->save();

                // Cascade ingredient active state to all branch inventories
                DB::table('branch_inventory')
                    ->where('ingredient_id', $ingredient->id)
                    ->update(['is_active' => $ingredient->is_active]);
            });

            return response()->json(['success' => true, 'is_active' => $ingredient->is_active]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function processStockOperation(Request $request)
    {
        $validated = $request->validate([
            'type'        => 'required|in:stock_in,stock_out,transfer',
            'branch'      => 'nullable|string',
            'from_branch' => 'nullable|string',
            'to_branch'   => 'nullable|string',
            'items'       => 'required|array',
            'items.*.key' => 'required|string',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit'=> 'nullable|string',
            'reason'      => 'nullable|string',
        ]);

        $unitMultipliers = [
            'L' => 1000, 'gal' => 3785.41, 'kg' => 1000, 'oz' => 28.3495,
            'ml' => 1, 'g' => 1, 'pc' => 1, 'pack' => 1, 'box' => 1,
        ];

        try {
            return DB::transaction(function () use ($validated, $unitMultipliers) {
                $type = $validated['type'];
                $authUserId = auth()->id();

                if ($type === 'stock_in') {
                    $branch = Branch::where('name', $validated['branch'])->firstOrFail();

                    foreach ($validated['items'] as $itemData) {
                        $ingredient = Ingredient::where('key', $itemData['key'])->firstOrFail();
                        $inputUnit = $itemData['unit'] ?? $ingredient->unit;
                        $qtyInBaseUnit = (float)$itemData['qty'] * ($unitMultipliers[$inputUnit] ?? 1);

                        DB::table('branch_inventory')
                            ->where('branch_id', $branch->id)
                            ->where('ingredient_id', $ingredient->id)
                            ->increment('stock', $qtyInBaseUnit);

                        StockMovement::create([
                            'ingredient_id' => $ingredient->id,
                            'user_id'       => $authUserId,
                            'change'        => $qtyInBaseUnit,
                            'reason'        => 'Restock @ ' . $branch->name,
                        ]);
                    }
                } elseif ($type === 'stock_out') {
                    $branch = Branch::where('name', $validated['branch'])->firstOrFail();

                    foreach ($validated['items'] as $itemData) {
                        $ingredient = Ingredient::where('key', $itemData['key'])->firstOrFail();
                        $inputUnit = $itemData['unit'] ?? $ingredient->unit;
                        $qtyInBaseUnit = (float)$itemData['qty'] * ($unitMultipliers[$inputUnit] ?? 1);

                        DB::table('branch_inventory')
                            ->where('branch_id', $branch->id)
                            ->where('ingredient_id', $ingredient->id)
                            ->decrement('stock', $qtyInBaseUnit);

                        StockMovement::create([
                            'ingredient_id' => $ingredient->id,
                            'user_id'       => $authUserId,
                            'change'        => -$qtyInBaseUnit,
                            'reason'        => ($validated['reason'] ?? 'Waste') . ' @ ' . $branch->name,
                        ]);
                    }
                } elseif ($type === 'transfer') {
                    $fromBranch = Branch::where('name', $validated['from_branch'])->firstOrFail();
                    $toBranch   = Branch::where('name', $validated['to_branch'])->firstOrFail();

                    foreach ($validated['items'] as $itemData) {
                        $ingredient = Ingredient::where('key', $itemData['key'])->firstOrFail();
                        $inputUnit = $itemData['unit'] ?? $ingredient->unit;
                        $qtyInBaseUnit = (float)$itemData['qty'] * ($unitMultipliers[$inputUnit] ?? 1);

                        $currentStock = DB::table('branch_inventory')
                            ->where('branch_id', $fromBranch->id)
                            ->where('ingredient_id', $ingredient->id)
                            ->value('stock') ?? 0;

                        if ($currentStock < $qtyInBaseUnit) {
                            return response()->json([
                                'success' => false,
                                'message' => "Cannot transfer {$ingredient->name}. {$fromBranch->name} only has {$currentStock}{$ingredient->unit} on hand."
                            ], 422);
                        }
                    }

                    foreach ($validated['items'] as $itemData) {
                        $ingredient = Ingredient::where('key', $itemData['key'])->firstOrFail();
                        $inputUnit = $itemData['unit'] ?? $ingredient->unit;
                        $qtyInBaseUnit = (float)$itemData['qty'] * ($unitMultipliers[$inputUnit] ?? 1);

                        DB::table('branch_inventory')
                            ->where('branch_id', $fromBranch->id)
                            ->where('ingredient_id', $ingredient->id)
                            ->decrement('stock', $qtyInBaseUnit);

                        DB::table('branch_inventory')
                            ->where('branch_id', $toBranch->id)
                            ->where('ingredient_id', $ingredient->id)
                            ->increment('stock', $qtyInBaseUnit);

                        // Outgoing movement for Source Branch
                        StockMovement::create([
                            'ingredient_id' => $ingredient->id,
                            'user_id'       => $authUserId,
                            'change'        => -$qtyInBaseUnit,
                            'reason'        => "Transfer: {$fromBranch->name} -> {$toBranch->name}",
                        ]);

                        // Incoming movement for Destination Branch
                        StockMovement::create([
                            'ingredient_id' => $ingredient->id,
                            'user_id'       => $authUserId,
                            'change'        => $qtyInBaseUnit,
                            'reason'        => "Transfer: {$fromBranch->name} -> {$toBranch->name}",
                        ]);
                    }
                }

                return response()->json(['success' => true]);
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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
            $branchUsed = $usedTodayMap->get($row->branch_id)?->where('ingredient_id', $row->ingredient_id)->first()?->total_used ?? 0;

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
            ->limit(300)
            ->get()
            ->map(function ($order) {
                $total = $order->items->sum(function ($item) {
                    $itemTotal = $item->unit_price * $item->quantity;
                    $addonTotal = $item->addons->sum('pivot.unit_price') * $item->quantity;
                    return $itemTotal + $addonTotal;
                });

                return [
                    'no'      => $order->order_no,
                    'date'    => $order->created_at->format('Y-m-d H:i'),
                    'staff'   => $order->staff->name ?? 'Unknown',
                    'branch'  => $order->branch->name ?? 'Unassigned',
                    'payment' => ucfirst($order->payment_method),
                    'gcash_ref' => $order->gcash_ref,
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
        return StockMovement::with(['ingredient', 'order.branch', 'user'])
            ->latest()
            ->limit(300)
            ->get()
            ->map(function ($m) {
                $branchName =$m->order->branch->name ?? null;
                $reason =$m->reason ?? '';

                if (!$branchName) {
                    if (preg_match('/Transfer:\s*(.+) -> (.+)/i', $reason,$matches)) {
                        $from = trim($matches[1]);
                        $to = trim($matches[2]);
                        $branchName = ($m->change < 0) ? $from :$to;
                    } elseif (preg_match('/@ (.+)/i', $reason, $matches)) {$branchName = trim($matches[1]);                     } else {$branchName = DB::table('branch_inventory')
                            ->join('branches', 'branch_inventory.branch_id', '=', 'branches.id')
                            ->where('branch_inventory.ingredient_id', $m->ingredient_id)
                            ->value('branches.name') ?? 'UM Matina Branch';
                    }
                }

                $performedBy = $m->user->name ?? ($m->order->staff->name ?? 'Admin User');

                return [
                    'id'           => $m->id,
                    'ingredient'   => $m->ingredient->name ?? 'Unknown',
                    'branch'       => $branchName,
                    'performed_by' => $performedBy,
                    'change'       => (float) $m->change,
                    'reason'       => ucfirst($reason ?: 'movement'),
                    'date'         => $m->created_at->format('Y-m-d H:i'),
                ];
            })->toArray();
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