<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ------------------------------ data ------------------------------ */

    private const BRANCHES = ['Poblacion Main', 'Mabini Branch', 'Uptown Terminal', 'Riverside Stall', 'Airport Kiosk'];

    /** Two staff per branch. Login = first name in lowercase (jhen, marco, kate...). */
    private const STAFF_BY_BRANCH = [
        'Poblacion Main'  => ['Jhen R.', 'Marco D.'],
        'Mabini Branch'   => ['Kate L.', 'Noel S.'],
        'Uptown Terminal' => ['Ria P.', 'Aldrin V.'],
        'Riverside Stall' => ['Trisha M.', 'Benjie C.'],
        'Airport Kiosk'   => ['Lia F.', 'Oscar T.'],
    ];

    private const DRINK_TYPES = [
        'Special Sundaes', 'Premium Sundaes', 'Iced Coffee Float', 'Non-Coffee Float', 'Frappe',
        'Iced Coffee', 'Hot Coffee', 'Matcha', 'Strawberry', 'Blueberry', 'Pistachio', 'Choco',
        'Flavored Soda', 'Pearl Shakes',
    ];

    /** key => [name, unit, stock, reorder level, cost per unit]. */
    private const INGREDIENTS = [
        'beans'     => ['Espresso beans',  'g',  2480,  1500, 1.20],
        'milk'      => ['Fresh milk',      'ml', 4200,  5000, 0.09],
        'caramel'   => ['Caramel syrup',   'ml', 620,   600,  0.35],
        'vanilla'   => ['Vanilla syrup',   'ml', 1150,  600,  0.35],
        'hazelnut'  => ['Hazelnut syrup',  'ml', 340,   600,  0.38],
        'choco'     => ['Chocolate sauce', 'ml', 980,   700,  0.42],
        'condensed' => ['Condensed milk',  'ml', 1320,  800,  0.28],
        'matcha'    => ['Matcha powder',   'g',  190,   250,  4.50],
        'cream'     => ['Whipping cream',  'g',  1450,  900,  0.65],
        'ice'       => ['Ice',             'g',  12000, 6000, 0.02],
        'cup16'     => ['Cups 16oz',       'pc', 143,   200,  4.50],
        'lid'       => ['Dome lids',       'pc', 410,   200,  1.75],
    ];

    /**
     * name => price (Grande / only size), venti price, has sizes, has hot/cold, category.
     * Taken from the menu board. Only the drinks in RECIPES below have a recipe so far.
     */
    private const MENU = [
        'Chocolate Sundae'      => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Strawberry Sundae'     => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Caramel Sundae'        => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Mango Sundae'          => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Blueberry Sundae'      => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],

        'Affogato'              => ['price' => 75,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Matcha Sundae'         => ['price' => 65,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Biscoff Overload'      => ['price' => 90,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Oreo Chocolate Sundae' => ['price' => 65,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],

        'Oreo Frappe'           => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Red Velvet Frappe'     => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Strawberry Frappe'     => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Matcha Frappe'         => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Vanilla Frappe'        => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Chocolate Frappe'      => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Cake Float'            => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Sprite Float'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Orange Float'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],

        'Caramel Macchiato'     => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Spanish Latte'         => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Mocha Latte'           => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Vanilla Latte'         => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Salted Caramel'        => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'White Chocolate'       => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Hazelnut'              => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Latte'                 => ['price' => 50,  'venti' => 60,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Americano'             => ['price' => 55,  'venti' => 65,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],

        'Choco Matcha'          => ['price' => 70,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Matcha'],
        'Matcha Latte'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => true,  'cat' => 'Matcha'],
        'Matcha Espresso'       => ['price' => 80,  'venti' => null, 'has_sizes' => false, 'has_temp' => true,  'cat' => 'Matcha'],
        'Strawberry Matcha'     => ['price' => 70,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Matcha'],

        'Strawberry'            => ['price' => 65,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Strawberry'],
        'Blueberry'             => ['price' => 65,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Blueberry'],

        'Pistachio Creme'       => ['price' => 60,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Pistachio'],
        'Pistachio Jolt'        => ['price' => 90,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Pistachio'],
        'Choco Stachio'         => ['price' => 75,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Pistachio'],

        'Lychee Soda'           => ['price' => 50,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Passion Fruit Soda'    => ['price' => 50,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Strawberry Soda'       => ['price' => 50,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Green Apple Soda'      => ['price' => 50,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
        'Blueberry Soda'        => ['price' => 50,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Flavored Soda'],
    ];

    /** What ONE serving uses: drink => [ingredient key => amount]. Add the rest from the admin Menu page. */
    private const RECIPES = [
        'Caramel Macchiato' => ['beans' => 18, 'milk' => 180, 'caramel' => 30,   'cup16' => 1, 'lid' => 1],
        'Spanish Latte'     => ['beans' => 18, 'milk' => 200, 'condensed' => 25, 'cup16' => 1, 'lid' => 1],
        'Mocha Latte'       => ['beans' => 18, 'milk' => 180, 'choco' => 30,     'cup16' => 1, 'lid' => 1],
        'Vanilla Latte'     => ['beans' => 18, 'milk' => 190, 'vanilla' => 25,   'cup16' => 1, 'lid' => 1],
        'White Chocolate'   => ['beans' => 14, 'milk' => 200, 'choco' => 35,     'cup16' => 1, 'lid' => 1],
        'Hazelnut'          => ['beans' => 18, 'milk' => 180, 'hazelnut' => 25,  'cup16' => 1, 'lid' => 1],
        'Matcha Latte'      => ['matcha' => 12, 'milk' => 210, 'cup16' => 1, 'lid' => 1],
        'Strawberry Frappe' => ['milk' => 120, 'ice' => 250, 'cream' => 20,      'cup16' => 1, 'lid' => 1],
    ];

    /** name => price, straight off the menu board's add-on boxes. */
    private const ADDONS = [
        'Extra Shot'    => 30,
        'Syrup'         => 10,
        'Ice Cream'     => 20,
        'Popping Boba'  => 15,
        'Rainbow Jelly' => 15,
    ];

    /** What one add-on uses. Popping Boba and Rainbow Jelly have no ingredient yet. */
    private const ADDON_RECIPES = [
        'Extra Shot' => ['beans' => 9],
        'Syrup'      => ['vanilla' => 15],
        'Ice Cream'  => ['cream' => 45],
    ];

    /** Which categories may offer each add-on: coffee add-ons vs soda add-ons on the board. */
    private const ADDON_CATEGORIES = [
        'Extra Shot'    => ['Iced Coffee'],
        'Syrup'         => ['Iced Coffee'],
        'Ice Cream'     => ['Iced Coffee'],
        'Popping Boba'  => ['Flavored Soda'],
        'Rainbow Jelly' => ['Flavored Soda'],
    ];

    /* ------------------------------- run ------------------------------ */

    public function run(): void
    {
        DB::transaction(function () {
            $branchIds     = $this->branches();
            $staffByBranch = $this->users($branchIds);
            $drinkTypeIds  = $this->drinkTypes();
            $ingredientIds = $this->ingredients();
            $menuIds       = $this->menu($drinkTypeIds, $ingredientIds);
            $addons        = $this->addons($ingredientIds, $drinkTypeIds);
            $this->orders($branchIds, $staffByBranch, $menuIds, $addons);
        });
    }

    /* ---------------------------------------------------------------- */

    private function branches(): array
    {
        foreach (self::BRANCHES as $name) {
            Branch::firstOrCreate(['name' => $name]);
        }

        return Branch::pluck('id', 'name')->all();   // ['Poblacion Main' => 1, ...]
    }

    /** Returns ['Poblacion Main' => [userId, userId], ...]. */
    private function users(array $branchIds): array
    {
        User::updateOrCreate(['contact' => 'admin'], [
            'name' => 'Admin User', 'username' => 'Admin',
            'password' => Hash::make('1234'), 'role' => 'admin', 'branch_id' => null,
        ]);

        User::updateOrCreate(['contact' => 'staff'], [
            'name' => 'Staff User', 'username' => 'Staff',
            'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $branchIds[self::BRANCHES[0]],
        ]);

        $byBranch = [];
        foreach (self::STAFF_BY_BRANCH as $branch => $names) {
            foreach ($names as $name) {
                $handle = strtolower(strtok($name, ' '));

                $byBranch[$branch][] = User::updateOrCreate(['contact' => $handle], [
                    'name' => $name, 'username' => ucfirst($handle),
                    'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $branchIds[$branch],
                ])->id;
            }
        }

        return $byBranch;
    }

    private function drinkTypes(): array
    {
        $ids = [];
        foreach (self::DRINK_TYPES as $name) {
            $ids[$name] = $this->put('drink_types', ['name' => $name]);
        }

        return $ids;
    }

    private function ingredients(): array
    {
        $ids = [];
        foreach (self::INGREDIENTS as $key => [$name, $unit, $stock, $reorder, $cost]) {
            $ids[$key] = $this->put('ingredients', ['key' => $key], [
                'name'          => $name,
                'unit'          => $unit,
                'stock'         => $stock,
                'reorder_level' => $reorder,
                'cost'          => $cost,
            ]);
        }

        return $ids;
    }

    private function menu(array $drinkTypeIds, array $ingredientIds): array
    {
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

            if (isset(self::RECIPES[$name])) {
                $this->recipe('menu_item_ingredient', 'menu_item_id', $id, self::RECIPES[$name], $ingredientIds);
            }
        }

        return $ids;
    }

    /** Returns ['Extra Shot' => ['id' => 1, 'price' => 30], ...] so orders can snapshot add-on prices. */
    private function addons(array $ingredientIds, array $drinkTypeIds): array
    {
        $out = [];
        foreach (self::ADDONS as $name => $price) {
            $id = $this->put('addons', ['name' => $name], ['price' => $price]);
            $out[$name] = ['id' => $id, 'price' => $price];

            $this->recipe('addon_ingredient', 'addon_id', $id, self::ADDON_RECIPES[$name] ?? [], $ingredientIds);

            DB::table('addon_drink_type')->where('addon_id', $id)->delete();
            $now = now();
            foreach (self::ADDON_CATEGORIES[$name] ?? [] as $category) {
                if (isset($drinkTypeIds[$category])) {
                    DB::table('addon_drink_type')->insert([
                        'addon_id'      => $id,
                        'drink_type_id' => $drinkTypeIds[$category],
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }

        return $out;
    }

    /**
     * A week of orders ending today, across every branch, following each
     * drink's size / hot-cold / add-on rules. The seed value is fixed, so a
     * reseed gives the same history. These do not deduct stock; live orders
     * from the staff screen do.
     */
    private function orders(array $branchIds, array $staffByBranch, array $menuIds, array $addons): void
    {
        if (DB::table('orders')->exists()) {
            return;
        }

        mt_srand(2026);
        $pick = fn (array $list) => $list[mt_rand(0, count($list) - 1)];

        $itemNames = array_keys(self::MENU);

        $addonsByCategory = [];
        foreach (self::ADDON_CATEGORIES as $addon => $categories) {
            foreach ($categories as $category) {
                $addonsByCategory[$category][] = $addon;
            }
        }

        // Plan every order first so the numbers can run in time order.
        $plans = [];
        foreach ($branchIds as $branch => $branchId) {
            $staff = $staffByBranch[$branch] ?? [];
            if (! $staff) {
                continue;
            }

            for ($ago = 6; $ago >= 0; $ago--) {
                $perDay = mt_rand(4, 8);

                for ($n = 0; $n < $perDay; $n++) {
                    $lastHour = $ago === 0 ? max(8, min(19, now()->hour)) : 19;
                    $at = now()->subDays($ago)->setTime(mt_rand(7, $lastHour), mt_rand(0, 59));
                    if ($at->isFuture()) {
                        $at = now()->subMinutes(mt_rand(5, 60));
                    }

                    $lines = [];
                    foreach (range(1, mt_rand(1, 3)) as $unused) {
                        $name = $pick($itemNames);
                        $item = self::MENU[$name];

                        $size = $item['has_sizes'] ? (mt_rand(0, 1) ? 'venti' : 'grande') : null;
                        $valid = $addonsByCategory[$item['cat']] ?? [];

                        $lines[] = [
                            'name'  => $name,
                            'qty'   => mt_rand(1, 2),
                            'size'  => $size,
                            'price' => ($size === 'venti' && $item['venti']) ? $item['venti'] : $item['price'],
                            'temp'  => $item['has_temp'] ? (mt_rand(0, 4) === 0 ? 'hot' : 'cold') : null,
                            'addon' => ($valid && mt_rand(0, 2) === 0) ? $pick($valid) : null,
                        ];
                    }

                    $plans[] = [
                        'at'      => $at,
                        'branch'  => $branchId,
                        'user'    => $pick($staff),
                        'payment' => mt_rand(0, 1) ? 'gcash' : 'cash',
                        'status'  => mt_rand(1, 20) === 1 ? 'refunded' : 'completed',
                        'lines'   => $lines,
                    ];
                }
            }
        }

        usort($plans, fn ($a, $b) => $a['at'] <=> $b['at']);

        foreach ($plans as $i => $plan) {
            $orderId = DB::table('orders')->insertGetId([
                'order_no'       => str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'user_id'        => $plan['user'],
                'branch_id'      => $plan['branch'],
                'payment_method' => $plan['payment'],
                'status'         => $plan['status'],
                'created_at'     => $plan['at'],
                'updated_at'     => $plan['at'],
            ]);

            foreach ($plan['lines'] as $line) {
                $itemId = DB::table('order_items')->insertGetId([
                    'order_id'     => $orderId,
                    'menu_item_id' => $menuIds[$line['name']],
                    'quantity'     => $line['qty'],
                    'unit_price'   => $line['price'],
                    'size'         => $line['size'],
                    'temperature'  => $line['temp'],
                    'created_at'   => $plan['at'],
                    'updated_at'   => $plan['at'],
                ]);

                if ($line['addon']) {
                    DB::table('order_item_addon')->insert([
                        'order_item_id' => $itemId,
                        'addon_id'      => $addons[$line['addon']]['id'],
                        'unit_price'    => $addons[$line['addon']]['price'],
                        'created_at'    => $plan['at'],
                        'updated_at'    => $plan['at'],
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