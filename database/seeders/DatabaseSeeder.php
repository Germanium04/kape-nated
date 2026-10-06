<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use App\Support\DemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One seeder for everything. Safe to run again: every table is matched on a
 * natural key (name / key / order_no) and updated or skipped, never duplicated.
 *
 * Order matters because of foreign keys:
 * branches -> users -> drink_types -> ingredients -> menu_items (+recipes)
 * -> addons (+recipes) -> orders (+items, +item addons)
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** DemoData has no drink type per item, so it is decided here. Edit freely. */
    private const DRINK_TYPE = [
        'Caramel Macchiato' => 'Iced Coffee',
        'Spanish Latte'     => 'Iced Coffee',
        'Mocha Latte'       => 'Iced Coffee',
        'Vanilla Latte'     => 'Iced Coffee',
        'Hazelnut'          => 'Iced Coffee',
        'White Chocolate'   => 'Choco',
        'Matcha Latte'      => 'Matcha',
        'Strawberry Frappe' => 'Frappe',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $branchIds     = $this->branches();
            $staffIds      = $this->users($branchIds);
            $drinkTypeIds  = $this->drinkTypes();
            $ingredientIds = $this->ingredients();
            $menuIds       = $this->menu($drinkTypeIds, $ingredientIds);
            $addonPrices   = $this->addons($ingredientIds);
            $this->orders($branchIds, $staffIds, $menuIds, $addonPrices);
        });
    }

    /* ---------------------------------------------------------------- */

    private function branches(): array
    {
        foreach (DemoData::branches() as $name) {
            Branch::firstOrCreate(['name' => $name]);
        }

        return Branch::pluck('id', 'name')->all();   // ['Poblacion Main' => 1, ...]
    }

    /** Returns ['Jhen R.' => userId, ...] for the staff named on the demo orders. */
    private function users(array $branchIds): array
    {
        $first = $branchIds[DemoData::branches()[0]];

        User::updateOrCreate(['contact' => 'admin'], [
            'name' => 'Admin User', 'username' => 'Admin',
            'password' => Hash::make('1234'), 'role' => 'admin', 'branch_id' => null,
        ]);

        User::updateOrCreate(['contact' => 'staff'], [
            'name' => 'Staff User', 'username' => 'Staff',
            'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $first,
        ]);

        // One login per name used on DemoData::orders(). contact = first name, password 1234.
        $ids = [];
        foreach (collect(DemoData::orders())->pluck('staff')->unique() as $name) {
            $handle = strtolower(strtok($name, ' '));
            $ids[$name] = User::updateOrCreate(['contact' => $handle], [
                'name' => $name, 'username' => ucfirst($handle),
                'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $first,
            ])->id;
        }

        return $ids;
    }

    private function drinkTypes(): array
    {
        $ids = [];
        foreach (DemoData::drinkTypes() as $name) {
            $ids[$name] = $this->put('drink_types', ['name' => $name]);
        }

        return $ids;
    }

    private function ingredients(): array
    {
        $ids = [];
        foreach (DemoData::ingredients() as $row) {
            $ids[$row['key']] = $this->put('ingredients', ['key' => $row['key']], [
                'name'          => $row['name'],
                'unit'          => $row['unit'],
                'stock'         => $row['stock'],
                'reorder_level' => $row['reorder'],
                'cost'          => $row['cost'],
                // 'used_today' is not stored; it is summed from stock_movements.
            ]);
        }

        return $ids;
    }

    private function menu(array $drinkTypeIds, array $ingredientIds): array
    {
        $ids = [];
        foreach (DemoData::menuItems() as $row) {
            $ids[$row['name']] = $id = $this->put('menu_items', ['name' => $row['name']], [
                'price'         => $row['price'],
                'drink_type_id' => $drinkTypeIds[self::DRINK_TYPE[$row['name']] ?? ''] ?? null,
                'is_active'     => true,
            ]);

            $this->recipe('menu_item_ingredient', 'menu_item_id', $id, $row['recipe'], $ingredientIds);
        }

        return $ids;
    }

    /** Returns ['Extra Shot' => 30.0, ...] so orders can snapshot add-on prices. */
    private function addons(array $ingredientIds): array
    {
        $recipes = DemoData::addonRecipes();
        $prices  = [];

        foreach (DemoData::customizations() as $row) {
            $id = $this->put('addons', ['name' => $row['name']], ['price' => $row['price']]);
            $prices[$row['name']] = ['id' => $id, 'price' => $row['price']];

            $this->recipe('addon_ingredient', 'addon_id', $id, $recipes[$row['name']] ?? [], $ingredientIds);
        }

        return $prices;
    }

    /**
     * Historical orders only. They do not deduct stock (they predate the stock
     * count above). Live orders from the staff screen do.
     */
    private function orders(array $branchIds, array $staffIds, array $menuIds, array $addons): void
    {
        foreach (DemoData::orders() as $o) {
            if (DB::table('orders')->where('order_no', $o['no'])->exists()) {
                continue;
            }

            $at = $o['date'];

            $orderId = DB::table('orders')->insertGetId([
                'order_no'       => $o['no'],
                'user_id'        => $staffIds[$o['staff']],
                'branch_id'      => $branchIds[$o['branch']],
                'payment_method' => strtolower($o['payment']),   // cash | gcash
                'status'         => strtolower($o['status']),    // completed | refunded
                'created_at'     => $at,
                'updated_at'     => $at,
            ]);

            foreach ($o['items'] as $i) {
                $itemId = DB::table('order_items')->insertGetId([
                    'order_id'     => $orderId,
                    'menu_item_id' => $menuIds[$i['name']],
                    'quantity'     => $i['qty'],
                    'unit_price'   => $i['price'],
                    'temperature'  => strtolower($i['temp']),
                    'created_at'   => $at,
                    'updated_at'   => $at,
                ]);

                foreach ($i['addons'] as $addonName) {
                    DB::table('order_item_addon')->insert([
                        'order_item_id' => $itemId,
                        'addon_id'      => $addons[$addonName]['id'],
                        'unit_price'    => $addons[$addonName]['price'],
                        'created_at'    => $at,
                        'updated_at'    => $at,
                    ]);
                }
            }
        }
    }

    /* ---------------------------- helpers ---------------------------- */

    /** Insert-or-update by $match, return the row id. */
    private function put(string $table, array $match, array $values = []): int
    {
        $now = now();
        $row = DB::table($table)->where($match)->first();

        if ($row) {
            DB::table($table)->where('id', $row->id)->update($values + ['updated_at' => $now]);
            return $row->id;
        }

        return DB::table($table)->insertGetId($match + $values + ['created_at' => $now, 'updated_at' => $now]);
    }

    /** Replace a recipe pivot (menu_item_ingredient / addon_ingredient) for one owner. */
    private function recipe(string $table, string $ownerCol, int $ownerId, array $recipe, array $ingredientIds): void
    {
        DB::table($table)->where($ownerCol, $ownerId)->delete();

        $now = now();
        DB::table($table)->insert(
            collect($recipe)->map(fn ($qty, $key) => [
                $ownerCol       => $ownerId,
                'ingredient_id' => $ingredientIds[$key],
                'quantity'      => $qty,
                'created_at'    => $now,
                'updated_at'    => $now,
            ])->values()->all()
        );
    }
}