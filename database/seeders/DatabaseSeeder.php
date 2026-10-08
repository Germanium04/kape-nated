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
 *
 * The menu below is the real board (photographed Sep 2026), not DemoData's
 * placeholder prices.
 *
 *  - price        = the base price (Grande, or the only price)
 *  - venti        = the Venti price, only for drinks that have sizes
 *  - has_sizes    = till shows a size choice (Grande / Venti, or Small cone / Giant swirl for Cones)
 *  - has_temp     = till shows a Hot / Cold choice
 *  - ADDON_TYPES  = which drink types each add-on may go on (addon_drink_type)
 *
 * Sundaes have no size, no temperature and no add-ons: one price each. The cone is the
 * only soft-serve item with a size choice.
 * Demo orders are generated for the LAST 7 DAYS relative to today (so the staff
 * dashboard always has data) and only when the orders table is empty. To get a
 * fresh set dated from today, run `php artisan migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** name => price, venti price, has_sizes, has_temp, drink type. */
    private const MENU = [
        // Special Sundaes — ₱39 each (single size, no hot option)
        'Chocolate Sundae'      => ['price' => 39, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Strawberry Sundae'     => ['price' => 39, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Caramel Sundae'        => ['price' => 39, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Mango Sundae'          => ['price' => 39, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Blueberry Sundae'      => ['price' => 39, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],

        // Soft serve cone — Small cone ₱18 / Giant swirl ₱28 (the only choice; no hot/cold, no add-ons)
        'Soft Serve Cone'       => ['price' => 18, 'venti' => 28, 'has_sizes' => true,  'has_temp' => false, 'cat' => 'Cones'],

        // Premium Sundaes (single size, no hot option)
        'Affogato'              => ['price' => 75, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Matcha Sundae'         => ['price' => 65, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Biscoff Overload'      => ['price' => 90, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Oreo Chocolate Sundae' => ['price' => 65, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],

        // Frappe & Floats
        'Oreo Frappe'           => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Red Velvet Frappe'     => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Strawberry Frappe'     => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Matcha Frappe'         => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Vanilla Frappe'        => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Chocolate Frappe'      => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Cake Float'            => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Sprite Float'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Orange Float'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],

        // Iced / Hot Coffee — Supports Grande (₱65) / Venti (₱85) & Hot/Cold
        'Caramel Macchiato'     => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Spanish Latte'         => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Mocha Latte'           => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Vanilla Latte'         => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Salted Caramel'        => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'White Chocolate'       => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Hazelnut'              => ['price' => 65, 'venti' => 85, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Latte'                 => ['price' => 50, 'venti' => 60, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],
        'Americano'             => ['price' => 55, 'venti' => 65, 'has_sizes' => true, 'has_temp' => true, 'cat' => 'Iced Coffee'],

        // Matcha
        'Choco Matcha'          => ['price' => 70, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Matcha'],
        'Matcha Latte'          => ['price' => 55, 'venti' => null, 'has_sizes' => false, 'has_temp' => true,  'cat' => 'Matcha'],
        'Matcha Espresso'       => ['price' => 80, 'venti' => null, 'has_sizes' => false, 'has_temp' => true,  'cat' => 'Matcha'],
        'Strawberry Matcha'     => ['price' => 70, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Matcha'],

        // Strawberry / Blueberry
        'Strawberry'            => ['price' => 65, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Strawberry'],
        'Blueberry'             => ['price' => 65, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Blueberry'],

        // Pistachio
        'Pistachio Creme'       => ['price' => 60, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Pistachio'],
        'Pistachio Jolt'        => ['price' => 90, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Pistachio'],
        'Choco Stachio'         => ['price' => 75, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Pistachio'],

        // Choco — ₱50 Grande / ₱65 Venti
        'Choco'                 => ['price' => 50, 'venti' => 65, 'has_sizes' => true,  'has_temp' => false, 'cat' => 'Choco'],

        // Flavored Soda — ₱50 Grande
        'Lychee Soda'           => ['price' => 50, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Passion Fruit Soda'    => ['price' => 50, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Strawberry Soda'       => ['price' => 50, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Green Apple Soda'      => ['price' => 50, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Blueberry Soda'        => ['price' => 50, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
    ];

    /** How often each drink type shows up in the generated demo orders. */
    private const WEIGHT = [
        'Iced Coffee' => 8, 'Matcha' => 4, 'Frappe' => 4, 'Special Sundaes' => 3, 'Cones' => 3,
        'Flavored Soda' => 3, 'Premium Sundaes' => 2, 'Choco' => 2, 'Strawberry' => 2,
        'Blueberry' => 2, 'Pistachio' => 2,
    ];

    /**
     * Which drink types each add-on is offered on (the board's "hot/iced coffee
     * add-ons" and "soda add-ons"). An add-on missing from this list would show
     * on every drink.
     */
    private const ADDON_TYPES = [
        'Extra Shot'    => ['Iced Coffee', 'Hot Coffee', 'Iced Coffee Float'],
        'Syrup'         => ['Iced Coffee', 'Hot Coffee', 'Iced Coffee Float'],
        'Ice Cream'     => ['Iced Coffee', 'Hot Coffee', 'Iced Coffee Float'],
        'Popping Boba'  => ['Flavored Soda'],
        'Rainbow Jelly' => ['Flavored Soda'],
    ];

    /**
     * These real menu items happen to share a name with one of DemoData's
     * original 8 placeholder recipes, so that invented ingredient breakdown
     * is reused here (at the real price above, not the old placeholder one).
     * Everything else on the real menu has no recipe yet — add it from the
     * admin Menu page once you know the real measurements.
     */
    private const REUSE_RECIPE = [
        'Caramel Macchiato', 'Spanish Latte', 'Mocha Latte', 'Vanilla Latte',
        'White Chocolate', 'Hazelnut', 'Matcha Latte', 'Strawberry Frappe',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $branchIds     = $this->branches();
            $staffIds      = $this->users($branchIds);
            $drinkTypeIds  = $this->drinkTypes();
            $ingredientIds = $this->ingredients();
            $menuIds       = $this->menu($drinkTypeIds, $ingredientIds);
            $addonPrices   = $this->addons($ingredientIds, $drinkTypeIds);
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

    /** Returns ['Staff User' => userId, 'Jhen R.' => userId, ...]: every staff login, used as demo cashiers. */
    private function users(array $branchIds): array
    {
        $first = $branchIds[DemoData::branches()[0]];

        User::updateOrCreate(['contact' => 'admin'], [
            'name' => 'Admin User', 'username' => 'Admin',
            'password' => Hash::make('1234'), 'role' => 'admin', 'branch_id' => null,
        ]);

        $ids = [];
        $ids['Staff User'] = User::updateOrCreate(['contact' => 'staff'], [
            'name' => 'Staff User', 'username' => 'Staff',
            'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $first,
        ])->id;

        // One login per name used on DemoData::orders(). contact = first name, password 1234.
        foreach (collect(DemoData::orders())->pluck('staff')->unique() as $name) {
            $handle = strtolower(strtok($name, ' '));
            $ids[$name] = User::updateOrCreate(['contact' => $handle], [
                'name' => $name, 'username' => ucfirst($handle),
                'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $first,
            ])->id;
        }

        return $ids;
    }

    /** DemoData's category list, plus "Pearl Shakes" and "Cones" which the real board has but DemoData didn't. */
    private function drinkTypes(): array
    {
        $ids = [];
        foreach ([...DemoData::drinkTypes(), 'Pearl Shakes', 'Cones'] as $name) {
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

    /** The real menu board. Recipes only exist for the 8 items listed in REUSE_RECIPE. */
    private function menu(array $drinkTypeIds, array $ingredientIds): array
    {
        $oldRecipes = DemoData::recipes();
        $ids = [];

        foreach (self::MENU as $name => $item) {
            $ids[$name] = $id = $this->put('menu_items', ['name' => $name], [
                'price'           => $item['price'],
                'price_venti'     => $item['venti'],
                'has_sizes'       => $item['has_sizes'],
                'has_temperature' => $item['has_temp'],
                'drink_type_id'   => $drinkTypeIds[$item['cat']] ?? null,
                'is_active'       => true,
            ]);

            if (in_array($name, self::REUSE_RECIPE, true)) {
                $this->recipe('menu_item_ingredient', 'menu_item_id', $id, $oldRecipes[$name], $ingredientIds);
            }
        }

        return $ids;
    }

    /** Returns ['Extra Shot' => ['id' => .., 'price' => ..], ...] so orders can snapshot add-on prices. */
    private function addons(array $ingredientIds, array $drinkTypeIds): array
    {
        $recipes = DemoData::addonRecipes();
        $prices  = [];
        $now     = now();

        foreach (DemoData::customizations() as $row) {
            $id = $this->put('addons', ['name' => $row['name']], ['price' => $row['price']]);
            $prices[$row['name']] = ['id' => $id, 'price' => $row['price']];

            $this->recipe('addon_ingredient', 'addon_id', $id, $recipes[$row['name']] ?? [], $ingredientIds);

            // Which drink types this add-on may go on.
            DB::table('addon_drink_type')->where('addon_id', $id)->delete();
            foreach (self::ADDON_TYPES[$row['name']] ?? [] as $type) {
                DB::table('addon_drink_type')->insert([
                    'addon_id'      => $id,
                    'drink_type_id' => $drinkTypeIds[$type],
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        return $prices;
    }

    /**
     * Demo order history for the last 7 days, dated relative to today so the
     * dashboards always have something to show. Repeatable (fixed random seed).
     * Skipped when orders already exist, so live orders from the till are never
     * mixed with it. These do not deduct stock.
     */
    private function orders(array $branchIds, array $staffIds, array $menuIds, array $addons): void
    {
        if (DB::table('orders')->exists()) {
            return;
        }

        mt_srand(2026);

        $cashiers = array_values($staffIds);
        $names    = array_keys(self::MENU);
        $weights  = array_map(fn ($n) => self::WEIGHT[self::MENU[$n]['cat']] ?? 2, $names);
        $busiest  = DemoData::branches()[0];

        $today = new \DateTimeImmutable('today');
        $now   = new \DateTimeImmutable();

        // 1) Work out when each order happened (never in the future), per branch and day.
        $slots = [];
        foreach ($branchIds as $branchName => $branchId) {
            for ($back = 6; $back >= 0; $back--) {
                $day = $today->modify("-{$back} days");
                $n   = $branchName === $busiest ? mt_rand(8, 14) : mt_rand(3, 7);

                for ($k = 0; $k < $n; $k++) {
                    $at = $day->setTime(mt_rand(7, 19), mt_rand(0, 59), mt_rand(0, 59));
                    if ($at <= $now) {
                        $slots[] = ['at' => $at, 'branch' => $branchId];
                    }
                }
            }
        }
        usort($slots, fn ($a, $b) => $a['at'] <=> $b['at']);   // oldest first, so order numbers climb with time

        // 2) Write them.
        foreach ($slots as $i => $slot) {
            $at = $slot['at']->format('Y-m-d H:i:s');

            $orderId = DB::table('orders')->insertGetId([
                'order_no'       => str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'user_id'        => $cashiers[mt_rand(0, count($cashiers) - 1)],
                'branch_id'      => $slot['branch'],
                'payment_method' => mt_rand(1, 100) <= 55 ? 'cash' : 'gcash',
                'status'         => mt_rand(1, 100) <= 4 ? 'refunded' : 'completed',
                'created_at'     => $at,
                'updated_at'     => $at,
            ]);

            $lines = mt_rand(1, 100) <= 60 ? 1 : (mt_rand(1, 100) <= 70 ? 2 : 3);

            for ($l = 0; $l < $lines; $l++) {
                $name = $this->pick($names, $weights);
                $meta = self::MENU[$name];

                $sizes = $meta['cat'] === 'Cones' ? ['small', 'giant'] : ['grande', 'venti'];   // same sets as StaffController
                $size  = $meta['has_sizes'] ? (mt_rand(1, 100) <= 30 ? $sizes[1] : $sizes[0]) : null;
                $price = ($size === $sizes[1] && $meta['venti'] !== null) ? $meta['venti'] : $meta['price'];
                $temp  = $meta['has_temp'] ? (mt_rand(1, 100) <= 25 ? 'hot' : 'cold') : null;

                $itemId = DB::table('order_items')->insertGetId([
                    'order_id'     => $orderId,
                    'menu_item_id' => $menuIds[$name],
                    'quantity'     => mt_rand(1, 100) <= 75 ? 1 : mt_rand(2, 3),
                    'unit_price'   => $price,
                    'size'         => $size,
                    'temperature'  => $temp,
                    'created_at'   => $at,
                    'updated_at'   => $at,
                ]);

                // Sometimes an add-on, but only one the till would offer on this drink type.
                $options = array_keys(array_filter(self::ADDON_TYPES, fn ($types) => in_array($meta['cat'], $types, true)));
                if ($options && mt_rand(1, 100) <= 25) {
                    $addonName = $options[mt_rand(0, count($options) - 1)];

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

    /** Weighted random choice from $names. */
    private function pick(array $names, array $weights): string
    {
        $r = mt_rand(1, array_sum($weights));

        foreach ($names as $i => $name) {
            $r -= $weights[$i];
            if ($r <= 0) {
                return $name;
            }
        }

        return end($names);
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