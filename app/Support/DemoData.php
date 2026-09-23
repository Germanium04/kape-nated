<?php

namespace App\Support;

/**
 * Front-end prototype data.
 *
 * Nothing here touches the database on purpose — swap each method for an
 * Eloquent query later (Order::with('items'), Ingredient::all(), etc.)
 * and the Blade views keep working as long as the array shape stays the same.
 */
class DemoData
{
    /** Ingredients consumed by one serving of each menu item. */
    public static function recipes(): array
    {
        return [
            'Caramel Macchiato' => ['beans' => 18, 'milk' => 180, 'caramel' => 30, 'cup16' => 1, 'lid' => 1],
            'Spanish Latte'     => ['beans' => 18, 'milk' => 200, 'condensed' => 25, 'cup16' => 1, 'lid' => 1],
            'Mocha Latte'       => ['beans' => 18, 'milk' => 180, 'choco' => 30, 'cup16' => 1, 'lid' => 1],
            'Vanilla Latte'     => ['beans' => 18, 'milk' => 190, 'vanilla' => 25, 'cup16' => 1, 'lid' => 1],
            'White Chocolate'   => ['beans' => 14, 'milk' => 200, 'choco' => 35, 'cup16' => 1, 'lid' => 1],
            'Hazelnut'          => ['beans' => 18, 'milk' => 180, 'hazelnut' => 25, 'cup16' => 1, 'lid' => 1],
            'Matcha Latte'      => ['matcha' => 12, 'milk' => 210, 'cup16' => 1, 'lid' => 1],
            'Strawberry Frappe' => ['milk' => 120, 'ice' => 250, 'cream' => 20, 'cup16' => 1, 'lid' => 1],
        ];
    }

    /** Add-ons cost stock too. */
    public static function addonRecipes(): array
    {
        return [
            'Extra Shot' => ['beans' => 9],
            'Syrup'      => ['vanilla' => 15],
            'Ice Cream'  => ['cream' => 45],
        ];
    }

    /** Current stock room. `used_today` is what the orders below already deducted. */
    public static function ingredients(): array
    {
        return [
            ['key' => 'beans',     'name' => 'Espresso beans',     'unit' => 'g',  'stock' => 2480, 'reorder' => 1500, 'used_today' => 520, 'cost' => 1.20],
            ['key' => 'milk',      'name' => 'Fresh milk',         'unit' => 'ml', 'stock' => 4200, 'reorder' => 5000, 'used_today' => 5380, 'cost' => 0.09],
            ['key' => 'caramel',   'name' => 'Caramel syrup',      'unit' => 'ml', 'stock' => 620,  'reorder' => 600,  'used_today' => 390, 'cost' => 0.35],
            ['key' => 'vanilla',   'name' => 'Vanilla syrup',      'unit' => 'ml', 'stock' => 1150, 'reorder' => 600,  'used_today' => 265, 'cost' => 0.35],
            ['key' => 'hazelnut',  'name' => 'Hazelnut syrup',     'unit' => 'ml', 'stock' => 340,  'reorder' => 600,  'used_today' => 175, 'cost' => 0.38],
            ['key' => 'choco',     'name' => 'Chocolate sauce',    'unit' => 'ml', 'stock' => 980,  'reorder' => 700,  'used_today' => 320, 'cost' => 0.42],
            ['key' => 'condensed', 'name' => 'Condensed milk',     'unit' => 'ml', 'stock' => 1320, 'reorder' => 800,  'used_today' => 225, 'cost' => 0.28],
            ['key' => 'matcha',    'name' => 'Matcha powder',      'unit' => 'g',  'stock' => 190,  'reorder' => 250,  'used_today' => 84,  'cost' => 4.50],
            ['key' => 'cream',     'name' => 'Whipping cream',     'unit' => 'g',  'stock' => 1450, 'reorder' => 900,  'used_today' => 380, 'cost' => 0.65],
            ['key' => 'ice',       'name' => 'Ice',                'unit' => 'g',  'stock' => 12000,'reorder' => 6000, 'used_today' => 3250,'cost' => 0.02],
            ['key' => 'cup16',     'name' => 'Cups 16oz',          'unit' => 'pc', 'stock' => 143,  'reorder' => 200,  'used_today' => 62,  'cost' => 4.50],
            ['key' => 'lid',       'name' => 'Dome lids',          'unit' => 'pc', 'stock' => 410,  'reorder' => 200,  'used_today' => 62,  'cost' => 1.75],
        ];
    }

    /** Combined view the Menu page manages: name, price, and the recipe from recipes(). */
    public static function menuItems(): array
    {
        $prices = [
            'Caramel Macchiato' => 150,
            'Spanish Latte'     => 140,
            'Mocha Latte'       => 145,
            'Vanilla Latte'     => 145,
            'White Chocolate'   => 150,
            'Hazelnut'          => 145,
            'Matcha Latte'      => 160,
            'Strawberry Frappe' => 165,
        ];

        $items = [];
        foreach (self::recipes() as $name => $recipe) {
            $items[] = [
                'name'   => $name,
                'price'  => $prices[$name] ?? 140,
                'recipe' => $recipe,
            ];
        }

        return $items;
    }

    /** The five stalls this admin account oversees. */
    public static function branches(): array
    {
        return ['Poblacion Main', 'Mabini Branch', 'Uptown Terminal', 'Riverside Stall', 'Airport Kiosk'];
    }

    /** Completed transactions the admin can pull a receipt from. */
    public static function orders(): array
    {
        return [
            [
                'no' => '0148', 'date' => '2026-09-19 14:12', 'staff' => 'Jhen R.', 'branch' => 'Poblacion Main', 'payment' => 'GCash', 'status' => 'Completed',
                'items' => [
                    ['name' => 'Caramel Macchiato', 'qty' => 2, 'price' => 150, 'temp' => 'Cold', 'addons' => ['Extra Shot']],
                    ['name' => 'Mocha Latte', 'qty' => 1, 'price' => 145, 'temp' => 'Hot', 'addons' => []],
                ],
            ],
            [
                'no' => '0147', 'date' => '2026-09-19 13:48', 'staff' => 'Marco D.', 'branch' => 'Mabini Branch', 'payment' => 'Cash', 'status' => 'Completed',
                'items' => [
                    ['name' => 'Spanish Latte', 'qty' => 1, 'price' => 140, 'temp' => 'Cold', 'addons' => ['Syrup']],
                    ['name' => 'Strawberry Frappe', 'qty' => 2, 'price' => 165, 'temp' => 'Cold', 'addons' => ['Ice Cream']],
                ],
            ],
            [
                'no' => '0146', 'date' => '2026-09-19 12:30', 'staff' => 'Jhen R.', 'branch' => 'Uptown Terminal', 'payment' => 'Cash', 'status' => 'Completed',
                'items' => [
                    ['name' => 'Matcha Latte', 'qty' => 3, 'price' => 160, 'temp' => 'Cold', 'addons' => []],
                ],
            ],
            [
                'no' => '0145', 'date' => '2026-09-19 11:05', 'staff' => 'Kate L.', 'branch' => 'Riverside Stall', 'payment' => 'GCash', 'status' => 'Refunded',
                'items' => [
                    ['name' => 'White Chocolate', 'qty' => 1, 'price' => 150, 'temp' => 'Hot', 'addons' => []],
                ],
            ],
            [
                'no' => '0144', 'date' => '2026-09-19 10:22', 'staff' => 'Marco D.', 'branch' => 'Airport Kiosk', 'payment' => 'Cash', 'status' => 'Completed',
                'items' => [
                    ['name' => 'Vanilla Latte', 'qty' => 2, 'price' => 145, 'temp' => 'Hot', 'addons' => []],
                    ['name' => 'Hazelnut', 'qty' => 1, 'price' => 145, 'temp' => 'Cold', 'addons' => ['Extra Shot']],
                ],
            ],
            [
                'no' => '0143', 'date' => '2026-09-18 17:40', 'staff' => 'Jhen R.', 'branch' => 'Poblacion Main', 'payment' => 'GCash', 'status' => 'Completed',
                'items' => [
                    ['name' => 'Caramel Macchiato', 'qty' => 1, 'price' => 150, 'temp' => 'Cold', 'addons' => []],
                    ['name' => 'Mocha Latte', 'qty' => 2, 'price' => 145, 'temp' => 'Cold', 'addons' => ['Syrup']],
                ],
            ],
        ];
    }

    /** Order total including add-ons (₱20 each, flat, for the prototype). */
    public static function total(array $order): float
    {
        $sum = 0;
        foreach ($order['items'] as $item) {
            $sum += $item['qty'] * ($item['price'] + (count($item['addons']) * 20));
        }

        return $sum;
    }

    /** Last 7 days of takings, for the sales chart. */
    public static function salesByDay(): array
    {
        return [
            ['label' => 'Sat 13', 'date' => '2026-09-13', 'cash' => 4820, 'gcash' => 3110],
            ['label' => 'Sun 14', 'date' => '2026-09-14', 'cash' => 6240, 'gcash' => 4880],
            ['label' => 'Mon 15', 'date' => '2026-09-15', 'cash' => 3180, 'gcash' => 2050],
            ['label' => 'Tue 16', 'date' => '2026-09-16', 'cash' => 3640, 'gcash' => 2470],
            ['label' => 'Wed 17', 'date' => '2026-09-17', 'cash' => 4110, 'gcash' => 3320],
            ['label' => 'Thu 18', 'date' => '2026-09-18', 'cash' => 5020, 'gcash' => 4260],
            ['label' => 'Fri 19', 'date' => '2026-09-19', 'cash' => 5890, 'gcash' => 5140],
        ];
    }

    /**
     * Rough share of total business each branch does, used only to scale the
     * demo numbers into a "per-branch" view. A real backend replaces every use
     * of this with an actual `WHERE branch_id = ?` query — nothing here should
     * survive past the prototype stage.
     */
    public static function branchWeights(): array
    {
        return [
            'Poblacion Main'  => 0.32,
            'Mabini Branch'   => 0.22,
            'Uptown Terminal' => 0.18,
            'Riverside Stall' => 0.16,
            'Airport Kiosk'   => 0.12,
        ];
    }

    /** Best sellers this week. */
    public static function topItems(): array
    {
        return [
            ['name' => 'Caramel Macchiato', 'sold' => 86, 'revenue' => 12900],
            ['name' => 'Spanish Latte',     'sold' => 71, 'revenue' => 9940],
            ['name' => 'Matcha Latte',      'sold' => 58, 'revenue' => 9280],
            ['name' => 'Mocha Latte',       'sold' => 49, 'revenue' => 7105],
            ['name' => 'Strawberry Frappe', 'sold' => 37, 'revenue' => 6105],
        ];
    }

    // public static function signup(string $fname, string $lname, string $contact, string $branch, string $password): array
    // {
    //     $user = [
    //         'name' => $fname . ' ' . $lname,
    //         'contact' => $contact,
    //         'branch' => $branch,
    //         'password' => $password,
    //     ];

    //     return $user;
    // }

}