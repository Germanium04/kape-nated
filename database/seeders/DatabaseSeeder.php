<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ------------------------------ data ------------------------------ */

    private const BRANCHES = ['Poblacion Main', 'Mabini Branch', 'Uptown Terminal', 'Riverside Stall', 'Airport Kiosk'];

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

    /** key => [name, unit_type, unit, stock, reorder level, cost per unit]. */
    private const INGREDIENTS = [
        'beans'       => ['Espresso beans',    'weight', 'g',  120000, 1500, 1.20],
        'milk'        => ['Fresh milk',        'volume', 'ml', 500000, 5000, 0.09],
        'caramel'     => ['Caramel syrup',     'volume', 'ml', 80000,  600,  0.35],
        'vanilla'     => ['Vanilla syrup',     'volume', 'ml', 80000,  600,  0.35],
        'hazelnut'    => ['Hazelnut syrup',    'volume', 'ml', 50000,  600,  0.38],
        'choco'       => ['Chocolate sauce',   'volume', 'ml', 90000,  700,  0.42],
        'white_choco' => ['White Choco sauce',  'volume', 'ml', 60000,  500,  0.45],
        'condensed'   => ['Condensed milk',    'volume', 'ml', 90000,  800,  0.28],
        'matcha'      => ['Matcha powder',     'weight', 'g',  40000,  250,  4.50],
        'cream'       => ['Whipping cream',    'weight', 'g',  80000,  900,  0.65],
        'ice_cream'   => ['Vanilla Ice Cream', 'weight', 'g',  150000, 1500, 0.30],
        'ice'         => ['Ice',               'weight', 'g',  500000, 6000, 0.02],
        'cup16'       => ['Cups 16oz',         'count',  'pc', 15000,  200,  4.50],
        'lid'         => ['Dome lids',         'count',  'pc', 15000,  200,  1.75],
        
        // Fruit/Flavor Purees & Mixes
        'strawberry'  => ['Strawberry jam',    'weight', 'g',  60000,  500,  0.50],
        'blueberry'   => ['Blueberry jam',     'weight', 'g',  60000,  500,  0.55],
        'mango'       => ['Mango puree',       'weight', 'g',  60000,  500,  0.48],
        'pistachio'   => ['Pistachio sauce',   'weight', 'g',  50000,  400,  0.85],
        'biscoff'     => ['Biscoff spread',    'weight', 'g',  40000,  300,  0.90],
        'oreo'        => ['Oreo crumbs',       'weight', 'g',  50000,  400,  0.30],
        
        // Sodas & Addons
        'soda_base'   => ['Soda water',        'volume', 'ml', 150000, 2000, 0.05],
        'lychee_syp'  => ['Lychee syrup',      'volume', 'ml', 50000,  400,  0.32],
        'passion_syp' => ['Passion fruit syp', 'volume', 'ml', 50000,  400,  0.32],
        'apple_syp'   => ['Green Apple syp',   'volume', 'ml', 50000,  400,  0.32],
        'boba'        => ['Popping Boba',      'weight', 'g',  50000,  500,  0.40],
        'jelly'       => ['Rainbow Jelly',     'weight', 'g',  50000,  500,  0.38],
    ];

    private const MENU = [
        // Special Sundaes
        'Chocolate Sundae'      => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Strawberry Sundae'     => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Caramel Sundae'        => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Mango Sundae'          => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],
        'Blueberry Sundae'      => ['price' => 39,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Special Sundaes'],

        // Premium Sundaes
        'Affogato'              => ['price' => 75,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Matcha Sundae'         => ['price' => 65,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Biscoff Overload'      => ['price' => 90,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],
        'Oreo Chocolate Sundae' => ['price' => 65,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Premium Sundaes'],

        // Frappes & Floats
        'Oreo Frappe'           => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Red Velvet Frappe'     => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Strawberry Frappe'     => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Matcha Frappe'         => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Vanilla Frappe'        => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Chocolate Frappe'      => ['price' => 130, 'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Cake Float'            => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Sprite Float'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],
        'Orange Float'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Frappe'],

        // Iced Coffee
        'Caramel Macchiato'     => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Spanish Latte'         => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Mocha Latte'           => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Vanilla Latte'         => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Salted Caramel'        => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'White Chocolate'       => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Hazelnut'              => ['price' => 65,  'venti' => 85,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Latte'                 => ['price' => 50,  'venti' => 60,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],
        'Americano'             => ['price' => 55,  'venti' => 65,   'has_sizes' => true,  'has_temp' => true,  'cat' => 'Iced Coffee'],

        // Hot Coffee
        'Hot Espresso'          => ['price' => 55,  'venti' => null, 'has_sizes' => false, 'has_temp' => false, 'cat' => 'Hot Coffee'],

        // Matcha & Flavored Series
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

    private const RECIPES = [
        'Chocolate Sundae'      => ['ice_cream' => 120, 'choco' => 25, 'cup16' => 1],
        'Strawberry Sundae'     => ['ice_cream' => 120, 'strawberry' => 30, 'cup16' => 1],
        'Caramel Sundae'        => ['ice_cream' => 120, 'caramel' => 25, 'cup16' => 1],
        'Mango Sundae'          => ['ice_cream' => 120, 'mango' => 30, 'cup16' => 1],
        'Blueberry Sundae'      => ['ice_cream' => 120, 'blueberry' => 30, 'cup16' => 1],
        'Affogato'              => ['ice_cream' => 140, 'beans' => 18, 'cup16' => 1],
        'Matcha Sundae'         => ['ice_cream' => 120, 'matcha' => 10, 'condensed' => 15, 'cup16' => 1],
        'Biscoff Overload'      => ['ice_cream' => 130, 'biscoff' => 35, 'cup16' => 1],
        'Oreo Chocolate Sundae' => ['ice_cream' => 120, 'choco' => 20, 'oreo' => 25, 'cup16' => 1],

        'Oreo Frappe'           => ['milk' => 150, 'ice' => 200, 'cream' => 25, 'oreo' => 30, 'cup16' => 1, 'lid' => 1],
        'Red Velvet Frappe'     => ['milk' => 160, 'ice' => 200, 'cream' => 25, 'vanilla' => 20, 'cup16' => 1, 'lid' => 1],
        'Strawberry Frappe'     => ['milk' => 140, 'ice' => 200, 'cream' => 25, 'strawberry' => 35, 'cup16' => 1, 'lid' => 1],
        'Matcha Frappe'         => ['milk' => 150, 'ice' => 200, 'cream' => 20, 'matcha' => 15, 'cup16' => 1, 'lid' => 1],
        'Vanilla Frappe'        => ['milk' => 160, 'ice' => 200, 'cream' => 25, 'vanilla' => 30, 'cup16' => 1, 'lid' => 1],
        'Chocolate Frappe'      => ['milk' => 150, 'ice' => 200, 'cream' => 25, 'choco' => 35, 'cup16' => 1, 'lid' => 1],
        'Cake Float'            => ['soda_base' => 180, 'ice_cream' => 80, 'cup16' => 1, 'lid' => 1],
        'Sprite Float'          => ['soda_base' => 200, 'ice_cream' => 80, 'cup16' => 1, 'lid' => 1],
        'Orange Float'          => ['soda_base' => 200, 'ice_cream' => 80, 'cup16' => 1, 'lid' => 1],

        'Caramel Macchiato'     => ['beans' => 18, 'milk' => 180, 'caramel' => 30, 'cup16' => 1, 'lid' => 1],
        'Spanish Latte'         => ['beans' => 18, 'milk' => 200, 'condensed' => 25, 'cup16' => 1, 'lid' => 1],
        'Mocha Latte'           => ['beans' => 18, 'milk' => 180, 'choco' => 30, 'cup16' => 1, 'lid' => 1],
        'Vanilla Latte'         => ['beans' => 18, 'milk' => 190, 'vanilla' => 25, 'cup16' => 1, 'lid' => 1],
        'Salted Caramel'        => ['beans' => 18, 'milk' => 180, 'caramel' => 25, 'cup16' => 1, 'lid' => 1],
        'White Chocolate'       => ['beans' => 14, 'milk' => 200, 'white_choco' => 35, 'cup16' => 1, 'lid' => 1],
        'Hazelnut'              => ['beans' => 18, 'milk' => 180, 'hazelnut' => 25, 'cup16' => 1, 'lid' => 1],
        'Latte'                 => ['beans' => 18, 'milk' => 220, 'cup16' => 1, 'lid' => 1],
        'Americano'             => ['beans' => 18, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Hot Espresso'          => ['beans' => 18, 'cup16' => 1],

        'Choco Matcha'          => ['matcha' => 10, 'milk' => 180, 'choco' => 25, 'cup16' => 1, 'lid' => 1],
        'Matcha Latte'          => ['matcha' => 12, 'milk' => 210, 'cup16' => 1, 'lid' => 1],
        'Matcha Espresso'       => ['matcha' => 10, 'beans' => 18, 'milk' => 180, 'cup16' => 1, 'lid' => 1],
        'Strawberry Matcha'     => ['matcha' => 10, 'milk' => 180, 'strawberry' => 30, 'cup16' => 1, 'lid' => 1],
        'Strawberry'            => ['milk' => 200, 'strawberry' => 40, 'cup16' => 1, 'lid' => 1],
        'Blueberry'             => ['milk' => 200, 'blueberry' => 40, 'cup16' => 1, 'lid' => 1],
        'Pistachio Creme'       => ['milk' => 200, 'pistachio' => 35, 'cream' => 15, 'cup16' => 1, 'lid' => 1],
        'Pistachio Jolt'        => ['beans' => 18, 'milk' => 180, 'pistachio' => 35, 'cup16' => 1, 'lid' => 1],
        'Choco Stachio'         => ['milk' => 180, 'choco' => 20, 'pistachio' => 25, 'cup16' => 1, 'lid' => 1],

        'Lychee Soda'           => ['soda_base' => 220, 'lychee_syp' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Passion Fruit Soda'    => ['soda_base' => 220, 'passion_syp' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Strawberry Soda'       => ['soda_base' => 220, 'strawberry' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Green Apple Soda'      => ['soda_base' => 220, 'apple_syp' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Blueberry Soda'        => ['soda_base' => 220, 'blueberry' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
    ];

    private const ADDONS = [
        'Extra Shot'    => 30,
        'Syrup'         => 10,
        'Ice Cream'     => 20,
        'Popping Boba'  => 15,
        'Rainbow Jelly' => 15,
    ];

    private const ADDON_RECIPES = [
        'Extra Shot'    => ['beans' => 9],
        'Syrup'         => ['vanilla' => 15],
        'Ice Cream'     => ['ice_cream' => 45],
        'Popping Boba'  => ['boba' => 30],
        'Rainbow Jelly' => ['jelly' => 30],
    ];

    private const ADDON_CATEGORIES = [
        'Extra Shot'    => ['Iced Coffee', 'Hot Coffee', 'Matcha'],
        'Syrup'         => ['Iced Coffee', 'Hot Coffee', 'Matcha'],
        'Ice Cream'     => ['Iced Coffee', 'Hot Coffee', 'Matcha'],
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
            
            // Seed branch-specific inventory stocks for every branch & ingredient
            $this->branchInventory($branchIds, $ingredientIds);

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

        return Branch::pluck('id', 'name')->all();
    }

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
        foreach (self::INGREDIENTS as $key => [$name, $unitType, $unit, $stock, $reorder, $cost]) {
            // Global master ingredient details
            $ids[$key] = $this->put('ingredients', ['key' => $key], [
                'name'          => $name,
                'unit_type'     => $unitType,
                'unit'          => $unit,
                'cost'          => $cost,
                'is_active'     => true,
            ]);
        }

        return $ids;
    }

    private function branchInventory(array $branchIds, array $ingredientIds): void
    {
        $now = Carbon::now('Asia/Manila');
        foreach ($branchIds as $branchName => $branchId) {
            foreach (self::INGREDIENTS as $key => [$name, $unitType, $unit, $stock, $reorder, $cost]) {
                DB::table('branch_inventory')->updateOrInsert(
                    [
                        'branch_id'     => $branchId,
                        'ingredient_id' => $ingredientIds[$key],
                    ],
                    [
                        'stock'         => $stock,
                        'reorder_level' => $reorder,
                        'is_active'     => true,
                        'updated_at'    => $now,
                        'created_at'    => $now,
                    ]
                );
            }
        }
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

    private function addons(array $ingredientIds, array $drinkTypeIds): array
    {
        $out = [];
        foreach (self::ADDONS as $name => $price) {
            $id = $this->put('addons', ['name' => $name], ['price' => $price]);
            $out[$name] = ['id' => $id, 'price' => $price];

            $this->recipe('addon_ingredient', 'addon_id', $id, self::ADDON_RECIPES[$name] ?? [], $ingredientIds);

            DB::table('addon_drink_type')->where('addon_id', $id)->delete();
            $now = Carbon::now('Asia/Manila');
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

    private function orders(array $branchIds, array $staffByBranch, array $menuIds, array $addons): void
    {
        if (DB::table('orders')->exists()) {
            return;
        }

        $pick = fn (array $list) => $list[array_rand($list)];

        $itemNames = array_keys(self::MENU);

        $addonsByCategory = [];
        foreach (self::ADDON_CATEGORIES as $addon => $categories) {
            foreach ($categories as $category) {
                $addonsByCategory[$category][] = $addon;
            }
        }

        $recipes      = self::RECIPES;
        $addonRecipes = self::ADDON_RECIPES;

        $plans = [];
        $nowPh = Carbon::now('Asia/Manila');

        foreach ($branchIds as $branch => $branchId) {
            $staff = $staffByBranch[$branch] ?? [];
            if (! $staff) {
                continue;
            }

            for ($ago = 6; $ago >= 0; $ago--) {
                $perDay = rand(3, 6);

                for ($n = 0; $n < $perDay; $n++) {
                    if ($ago === 0) {
                        // For TODAY: Spread orders up to current hour
                        $maxHour = max(7, $nowPh->hour);
                        $at = $nowPh->copy()->setTime(rand(7, $maxHour), rand(0, 59));
                        if ($at->isFuture()) {
                            $at = $nowPh->copy()->subMinutes(rand(2, 45));
                        }
                    } else {
                        // For PREVIOUS DAYS: Spread orders between 7 AM and 7 PM
                        $at = $nowPh->copy()->subDays($ago)->setTime(rand(7, 19), rand(0, 59));
                    }

                    $lines = [];
                    foreach (range(1, rand(1, 3)) as $unused) {
                        $name = $pick($itemNames);
                        $item = self::MENU[$name];

                        $size = $item['has_sizes'] ? (rand(0, 1) ? 'venti' : 'grande') : null;
                        $valid = $addonsByCategory[$item['cat']] ?? [];

                        $lines[] = [
                            'name'  => $name,
                            'qty'   => rand(1, 2),
                            'size'  => $size,
                            'price' => ($size === 'venti' && $item['venti']) ? $item['venti'] : $item['price'],
                            'temp'  => $item['has_temp'] ? (rand(0, 4) === 0 ? 'hot' : 'cold') : null,
                            'addon' => ($valid && rand(0, 2) === 0) ? $pick($valid) : null,
                        ];
                    }

                    $plans[] = [
                        'at'      => $at,
                        'branch'  => $branchId,
                        'user'    => $pick($staff),
                        'payment' => rand(0, 1) ? 'gcash' : 'cash',
                        'status'  => rand(1, 20) === 1 ? 'refunded' : 'completed',
                        'lines'   => $lines,
                    ];
                }
            }
        }

        usort($plans, fn ($a, $b) => $a['at'] <=> $b['at']);

        $ingredientKeyToId = DB::table('ingredients')->pluck('id', 'key')->all();

        foreach ($plans as $i => $plan) {
            $branchId = $plan['branch'];

            $orderId = DB::table('orders')->insertGetId([
                'order_no'       => str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'user_id'        => $plan['user'],
                'branch_id'      => $branchId,
                'payment_method' => $plan['payment'],
                'status'         => $plan['status'],
                'created_at'     => $plan['at'],
                'updated_at'     => $plan['at'],
            ]);

            $orderNeed = [];

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

                    foreach ($addonRecipes[$line['addon']] ?? [] as $ingKey => $qty) {
                        if (isset($ingredientKeyToId[$ingKey])) {
                            $ingId = $ingredientKeyToId[$ingKey];
                            $orderNeed[$ingId] = ($orderNeed[$ingId] ?? 0) + ($qty * $line['qty']);
                        }
                    }
                }

                foreach ($recipes[$line['name']] ?? [] as $ingKey => $qty) {
                    if (isset($ingredientKeyToId[$ingKey])) {
                        $ingId = $ingredientKeyToId[$ingKey];
                        $orderNeed[$ingId] = ($orderNeed[$ingId] ?? 0) + ($qty * $line['qty']);
                    }
                }
            }

            if ($plan['status'] === 'completed') {
                foreach ($orderNeed as $ingId => $qtyNeeded) {
                    DB::table('branch_inventory')
                        ->where('branch_id', $branchId)
                        ->where('ingredient_id', $ingId)
                        ->decrement('stock', $qtyNeeded);

                    DB::table('stock_movements')->insert([
                        'ingredient_id' => $ingId,
                        'order_id'      => $orderId,
                        'change'        => -$qtyNeeded,
                        'reason'        => 'order',
                        'created_at'    => $plan['at'],
                        'updated_at'    => $plan['at'],
                    ]);
                }
            }
        }
    }
    
    /* ---------------------------- helpers ---------------------------- */

    private function put(string $table, array $match, array $values = []): int
    {
        $now = Carbon::now('Asia/Manila');
        $row = DB::table($table)->where($match)->first();

        if ($row) {
            DB::table($table)->where('id', $row->id)->update($values + ['updated_at' => $now]);
            return $row->id;
        }

        return DB::table($table)->insertGetId($match + $values + ['created_at' => $now, 'updated_at' => $now]);
    }

    private function recipe(string $table, string $ownerCol, int $ownerId, array $recipe, array $ingredientIds): void
    {
        DB::table($table)->where($ownerCol, $ownerId)->delete();

        $now = Carbon::now('Asia/Manila');
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