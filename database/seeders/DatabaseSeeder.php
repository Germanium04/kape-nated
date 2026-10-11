<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Kape-nated demo data, built from the printed menu board.
 *
 * Run a clean seed with:  php artisan migrate:fresh --seed
 *
 * What it creates
 *  - 5 branches, 1 admin, 1 "Staff" demo account + 2 staff per branch
 *  - every drink on the board (incl. Cones, Hot Coffee, Pearl Shakes, Choco)
 *  - ingredients, recipes, add-ons, add-on <-> drink type rules
 *  - per-branch inventory driven by a real day-by-day simulation
 *  - ~75 days of orders, order items, add-ons and stock movements that
 *    reconcile exactly with branch_inventory.stock
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const TZ           = 'Asia/Manila';
    private const DT           = 'Y-m-d H:i:s';
    private const HISTORY_DAYS = 75;
    private const BASE_ORDERS  = 22;   // orders/day at the flagship branch (scale 1.00)

    /** branch name => sales volume scale (Poblacion Main is the flagship) */
    private const BRANCHES = [
        'Poblacion Main'  => 1.00,
        'Mabini Branch'   => 0.60,
        'Uptown Terminal' => 0.50,
        'Riverside Stall' => 0.38,
        'Airport Kiosk'   => 0.45,
    ];

    /** The generic "Staff" demo login is added to Poblacion Main automatically. */
    private const STAFF_BY_BRANCH = [
        'Poblacion Main'  => ['Jhen R.', 'Marco D.'],
        'Mabini Branch'   => ['Kate L.', 'Noel S.'],
        'Uptown Terminal' => ['Ria P.', 'Aldrin V.'],
        'Riverside Stall' => ['Trisha M.', 'Benjie C.'],
        'Airport Kiosk'   => ['Lia F.', 'Oscar T.'],
    ];

    /** Names must match what StaffController expects ('Cones' drives the small/giant sizes). */
    private const DRINK_TYPES = [
        'Special Sundaes', 'Premium Sundaes', 'Cones', 'Frappe',
        'Iced Coffee Float', 'Non-Coffee Float',
        'Iced Coffee', 'Hot Coffee',
        'Matcha', 'Strawberry', 'Blueberry', 'Pistachio', 'Choco',
        'Pearl Shakes', 'Flavored Soda',
    ];

    /**
     * key => [name, unit_type, unit, purchase_unit, conversion_factor, cost per base unit, est. daily use at flagship]
     * conversion_factor = how many base units one purchase unit contains (used by the restock modal).
     */
    private const INGREDIENTS = [
        'beans'       => ['Espresso beans',     'weight', 'g',  '1Kg Bag',      1000, 1.20, 450],
        'milk'        => ['Fresh milk',         'volume', 'ml', '1L Carton',    1000, 0.09, 6000],
        'cream'       => ['Whipping cream',     'weight', 'g',  '1L Carton',    1000, 0.65, 200],
        'condensed'   => ['Condensed milk',     'volume', 'ml', '1L Pack',      1000, 0.28, 150],
        'caramel'     => ['Caramel syrup',      'volume', 'ml', '1L Bottle',    1000, 0.35, 150],
        'vanilla'     => ['Vanilla syrup',      'volume', 'ml', '1L Bottle',    1000, 0.35, 250],
        'hazelnut'    => ['Hazelnut syrup',     'volume', 'ml', '1L Bottle',    1000, 0.38, 100],
        'choco'       => ['Chocolate sauce',    'volume', 'ml', '2L Jug',       2000, 0.42, 400],
        'white_choco' => ['White Choco sauce',  'volume', 'ml', '2L Jug',       2000, 0.45, 120],
        'matcha'      => ['Matcha powder',      'weight', 'g',  '500g Bag',     500,  4.50, 90],
        'taro'        => ['Taro powder',        'weight', 'g',  '500g Bag',     500,  1.60, 40],
        'red_velvet'  => ['Red velvet powder',  'weight', 'g',  '500g Bag',     500,  1.40, 40],
        'ice_cream'   => ['Vanilla Ice Cream',  'weight', 'g',  '1.5Kg Tub',    1500, 0.30, 1600],
        'ice'         => ['Ice',                'weight', 'g',  '5Kg Bag',      5000, 0.02, 3500],
        'cup16'       => ['Cups 16oz',          'count',  'pc', '50pc Sleeve',  50,   4.50, 55],
        'lid'         => ['Dome lids',          'count',  'pc', '100pc Sleeve', 100,  1.75, 45],
        'cone'        => ['Cone wafers',        'count',  'pc', '100pc Box',    100,  3.00, 8],
        'strawberry'  => ['Strawberry jam',     'weight', 'g',  '1Kg Jar',      1000, 0.50, 220],
        'blueberry'   => ['Blueberry jam',      'weight', 'g',  '1Kg Jar',      1000, 0.55, 160],
        'mango'       => ['Mango puree',        'weight', 'g',  '1Kg Pack',     1000, 0.48, 80],
        'pistachio'   => ['Pistachio sauce',    'weight', 'g',  '1Kg Tub',      1000, 0.85, 130],
        'biscoff'     => ['Biscoff spread',     'weight', 'g',  '1.6Kg Tub',    1600, 0.90, 70],
        'oreo'        => ['Oreo crumbs',        'weight', 'g',  '500g Pack',    500,  0.30, 160],
        'pearls'      => ['Tapioca pearls',     'weight', 'g',  '1Kg Bag',      1000, 0.25, 150],
        'soda_base'   => ['Soda water',         'volume', 'ml', '1.5L Bottle',  1500, 0.05, 800],
        'cola'        => ['Cola',               'volume', 'ml', '1.5L Bottle',  1500, 0.06, 120],
        'lemon_lime'  => ['Lemon-lime soda',    'volume', 'ml', '1.5L Bottle',  1500, 0.06, 120],
        'orange_soda' => ['Orange soda',        'volume', 'ml', '1.5L Bottle',  1500, 0.06, 120],
        'lychee_syp'  => ['Lychee syrup',       'volume', 'ml', '1L Bottle',    1000, 0.32, 70],
        'passion_syp' => ['Passion fruit syp',  'volume', 'ml', '1L Bottle',    1000, 0.32, 70],
        'apple_syp'   => ['Green Apple syp',    'volume', 'ml', '1L Bottle',    1000, 0.32, 70],
        'boba'        => ['Popping Boba',       'weight', 'g',  '1Kg Tub',      1000, 0.40, 70],
        'jelly'       => ['Rainbow Jelly',      'weight', 'g',  '1Kg Tub',      1000, 0.38, 70],
    ];

    /**
     * name => [price (grande/base), price_venti, has_sizes, has_temperature, drink type]
     * Listed in the same order as the board so the Order screen reads naturally.
     *
     * Hot Coffee is its own set of items because the board charges 75 for hot
     * (vs 65 iced) and the schema has no per-temperature price.
     * Cones: small = price, giant = price_venti (StaffController maps the sizes).
     */
    private const MENU = [
        // --- Special Sundae (39) ---
        'Chocolate Sundae'          => [39,  null, false, false, 'Special Sundaes'],
        'Strawberry Sundae'         => [39,  null, false, false, 'Special Sundaes'],
        'Caramel Sundae'            => [39,  null, false, false, 'Special Sundaes'],
        'Mango Sundae'              => [39,  null, false, false, 'Special Sundaes'],
        'Blueberry Sundae'          => [39,  null, false, false, 'Special Sundaes'],
        // --- Premium Sundae ---
        'Affogato'                  => [75,  null, false, false, 'Premium Sundaes'],
        'Matcha Sundae'             => [65,  null, false, false, 'Premium Sundaes'],
        'Biscoff Overload'          => [90,  null, false, false, 'Premium Sundaes'],
        'Oreo Chocolate Sundae'     => [65,  null, false, false, 'Premium Sundaes'],
        // --- Cones: small 18 / giant swirl 28 ---
        'Soft Serve Cone'           => [18,  28,   true,  false, 'Cones'],
        // --- Frappe (130, venti) ---
        'Oreo Frappe'               => [130, null, false, false, 'Frappe'],
        'Red Velvet Frappe'         => [130, null, false, false, 'Frappe'],
        'Strawberry Frappe'         => [130, null, false, false, 'Frappe'],
        'Matcha Frappe'             => [130, null, false, false, 'Frappe'],
        'Vanilla Frappe'            => [130, null, false, false, 'Frappe'],
        'Chocolate Frappe'          => [130, null, false, false, 'Frappe'],
        // --- Non-coffee floats (55) ---
        'Cake Float'                => [55,  null, false, false, 'Non-Coffee Float'],
        'Sprite Float'              => [55,  null, false, false, 'Non-Coffee Float'],
        'Orange Float'              => [55,  null, false, false, 'Non-Coffee Float'],
        // --- Iced Coffee 65 grande / 85 venti ---
        'Caramel Macchiato'         => [65,  85,   true,  false, 'Iced Coffee'],
        'Spanish Latte'             => [65,  85,   true,  false, 'Iced Coffee'],
        'Mocha Latte'               => [65,  85,   true,  false, 'Iced Coffee'],
        'Vanilla Latte'             => [65,  85,   true,  false, 'Iced Coffee'],
        'Salted Caramel'            => [65,  85,   true,  false, 'Iced Coffee'],
        'White Chocolate'           => [65,  85,   true,  false, 'Iced Coffee'],
        'Hazelnut'                  => [65,  85,   true,  false, 'Iced Coffee'],
        'Latte'                     => [50,  60,   true,  true,  'Iced Coffee'],   // iced / hot
        'Americano'                 => [55,  null, false, true,  'Iced Coffee'],
        // --- Hot Coffee 75 grande ---
        'Hot Caramel Macchiato'     => [75,  null, false, false, 'Hot Coffee'],
        'Hot Spanish Latte'         => [75,  null, false, false, 'Hot Coffee'],
        'Hot Mocha Latte'           => [75,  null, false, false, 'Hot Coffee'],
        'Hot Vanilla Latte'         => [75,  null, false, false, 'Hot Coffee'],
        'Hot Salted Caramel'        => [75,  null, false, false, 'Hot Coffee'],
        'Hot White Chocolate'       => [75,  null, false, false, 'Hot Coffee'],
        'Hot Hazelnut'              => [75,  null, false, false, 'Hot Coffee'],
        // --- Matcha ---
        'Choco Matcha'              => [70,  null, false, false, 'Matcha'],
        'Matcha Latte'              => [55,  null, false, false, 'Matcha'],
        'Matcha Espresso'           => [80,  null, false, false, 'Matcha'],
        'Strawberry Matcha'         => [70,  null, false, false, 'Matcha'],
        // --- Strawberry / Blueberry ---
        'Strawberry'                => [65,  null, false, false, 'Strawberry'],
        'Blueberry'                 => [65,  null, false, false, 'Blueberry'],
        // --- Pistachio (NEW) ---
        'Pistachio Creme'           => [60,  null, false, false, 'Pistachio'],
        'Pistachio Jolt'            => [90,  null, false, false, 'Pistachio'],
        'Choco Stachio'             => [75,  null, false, false, 'Pistachio'],
        // --- Choco 50 grande / 65 venti ---
        'Iced Choco'                => [50,  65,   true,  false, 'Choco'],
        // --- Pearl Shakes (55) ---
        'Cookies & Cream Pearl Shake' => [55, null, false, false, 'Pearl Shakes'],
        'Chocolate Pearl Shake'     => [55,  null, false, false, 'Pearl Shakes'],
        'Matcha Pearl Shake'        => [55,  null, false, false, 'Pearl Shakes'],
        'Vanilla Pearl Shake'       => [55,  null, false, false, 'Pearl Shakes'],
        'Strawberry Pearl Shake'    => [55,  null, false, false, 'Pearl Shakes'],
        'Taro Pearl Shake'          => [55,  null, false, false, 'Pearl Shakes'],
        // --- Flavored Soda (50) ---
        'Lychee Soda'               => [50,  null, false, false, 'Flavored Soda'],
        'Passion Fruit Soda'        => [50,  null, false, false, 'Flavored Soda'],
        'Strawberry Soda'           => [50,  null, false, false, 'Flavored Soda'],
        'Green Apple Soda'          => [50,  null, false, false, 'Flavored Soda'],
        'Blueberry Soda'            => [50,  null, false, false, 'Flavored Soda'],
    ];

    /** Shown as "Unavailable" on the Order screen (the board has an N/A sticker on the pearl shakes). */
    private const INACTIVE_ITEMS = ['Strawberry Pearl Shake'];

    /**
     * Per-serving recipes. "Hot ..." items are derived automatically from the
     * iced version minus the ice (see fullRecipes()).
     */
    private const RECIPES = [
        'Chocolate Sundae'            => ['ice_cream' => 120, 'choco' => 25, 'cup16' => 1],
        'Strawberry Sundae'           => ['ice_cream' => 120, 'strawberry' => 30, 'cup16' => 1],
        'Caramel Sundae'              => ['ice_cream' => 120, 'caramel' => 25, 'cup16' => 1],
        'Mango Sundae'                => ['ice_cream' => 120, 'mango' => 30, 'cup16' => 1],
        'Blueberry Sundae'            => ['ice_cream' => 120, 'blueberry' => 30, 'cup16' => 1],
        'Affogato'                    => ['ice_cream' => 140, 'beans' => 18, 'cup16' => 1],
        'Matcha Sundae'               => ['ice_cream' => 120, 'matcha' => 10, 'condensed' => 15, 'cup16' => 1],
        'Biscoff Overload'            => ['ice_cream' => 130, 'biscoff' => 35, 'cup16' => 1],
        'Oreo Chocolate Sundae'       => ['ice_cream' => 120, 'choco' => 20, 'oreo' => 25, 'cup16' => 1],
        'Soft Serve Cone'             => ['ice_cream' => 90, 'cone' => 1],

        'Oreo Frappe'                 => ['milk' => 150, 'ice' => 200, 'cream' => 25, 'oreo' => 30, 'cup16' => 1, 'lid' => 1],
        'Red Velvet Frappe'           => ['milk' => 160, 'ice' => 200, 'cream' => 25, 'red_velvet' => 20, 'cup16' => 1, 'lid' => 1],
        'Strawberry Frappe'           => ['milk' => 140, 'ice' => 200, 'cream' => 25, 'strawberry' => 35, 'cup16' => 1, 'lid' => 1],
        'Matcha Frappe'               => ['milk' => 150, 'ice' => 200, 'cream' => 20, 'matcha' => 15, 'cup16' => 1, 'lid' => 1],
        'Vanilla Frappe'              => ['milk' => 160, 'ice' => 200, 'cream' => 25, 'vanilla' => 30, 'cup16' => 1, 'lid' => 1],
        'Chocolate Frappe'            => ['milk' => 150, 'ice' => 200, 'cream' => 25, 'choco' => 35, 'cup16' => 1, 'lid' => 1],

        'Cake Float'                  => ['cola' => 180, 'ice_cream' => 80, 'cup16' => 1, 'lid' => 1],
        'Sprite Float'                => ['lemon_lime' => 200, 'ice_cream' => 80, 'cup16' => 1, 'lid' => 1],
        'Orange Float'                => ['orange_soda' => 200, 'ice_cream' => 80, 'cup16' => 1, 'lid' => 1],

        'Caramel Macchiato'           => ['beans' => 18, 'milk' => 180, 'caramel' => 30, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Spanish Latte'               => ['beans' => 18, 'milk' => 200, 'condensed' => 25, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Mocha Latte'                 => ['beans' => 18, 'milk' => 180, 'choco' => 30, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Vanilla Latte'               => ['beans' => 18, 'milk' => 190, 'vanilla' => 25, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Salted Caramel'              => ['beans' => 18, 'milk' => 180, 'caramel' => 25, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'White Chocolate'             => ['beans' => 14, 'milk' => 200, 'white_choco' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Hazelnut'                    => ['beans' => 18, 'milk' => 180, 'hazelnut' => 25, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Latte'                       => ['beans' => 18, 'milk' => 220, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Americano'                   => ['beans' => 18, 'ice' => 150, 'cup16' => 1, 'lid' => 1],

        'Choco Matcha'                => ['matcha' => 10, 'milk' => 180, 'choco' => 25, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Matcha Latte'                => ['matcha' => 12, 'milk' => 210, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Matcha Espresso'             => ['matcha' => 10, 'beans' => 18, 'milk' => 180, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Strawberry Matcha'           => ['matcha' => 10, 'milk' => 180, 'strawberry' => 30, 'ice' => 150, 'cup16' => 1, 'lid' => 1],

        'Strawberry'                  => ['milk' => 200, 'strawberry' => 40, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Blueberry'                   => ['milk' => 200, 'blueberry' => 40, 'ice' => 150, 'cup16' => 1, 'lid' => 1],

        'Pistachio Creme'             => ['milk' => 200, 'pistachio' => 35, 'cream' => 15, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Pistachio Jolt'              => ['beans' => 18, 'milk' => 180, 'pistachio' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Choco Stachio'               => ['milk' => 180, 'choco' => 20, 'pistachio' => 25, 'ice' => 150, 'cup16' => 1, 'lid' => 1],

        'Iced Choco'                  => ['milk' => 200, 'choco' => 40, 'ice' => 150, 'cup16' => 1, 'lid' => 1],

        'Cookies & Cream Pearl Shake' => ['milk' => 160, 'ice' => 180, 'pearls' => 40, 'oreo' => 30, 'cream' => 15, 'cup16' => 1, 'lid' => 1],
        'Chocolate Pearl Shake'       => ['milk' => 160, 'ice' => 180, 'pearls' => 40, 'choco' => 35, 'cup16' => 1, 'lid' => 1],
        'Matcha Pearl Shake'          => ['milk' => 160, 'ice' => 180, 'pearls' => 40, 'matcha' => 12, 'cup16' => 1, 'lid' => 1],
        'Vanilla Pearl Shake'         => ['milk' => 160, 'ice' => 180, 'pearls' => 40, 'vanilla' => 30, 'cup16' => 1, 'lid' => 1],
        'Strawberry Pearl Shake'      => ['milk' => 160, 'ice' => 180, 'pearls' => 40, 'strawberry' => 35, 'cup16' => 1, 'lid' => 1],
        'Taro Pearl Shake'            => ['milk' => 160, 'ice' => 180, 'pearls' => 40, 'taro' => 20, 'cup16' => 1, 'lid' => 1],

        'Lychee Soda'                 => ['soda_base' => 220, 'lychee_syp' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Passion Fruit Soda'          => ['soda_base' => 220, 'passion_syp' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Strawberry Soda'             => ['soda_base' => 220, 'strawberry' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Green Apple Soda'            => ['soda_base' => 220, 'apple_syp' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
        'Blueberry Soda'              => ['soda_base' => 220, 'blueberry' => 35, 'ice' => 150, 'cup16' => 1, 'lid' => 1],
    ];

    // Board: hot/iced coffee add-ons = extra shot 30, syrup 10, ice cream 20, (+15 boba);
    //        soda add-ons = popping boba 15, rainbow jelly 15;
    //        floats = "all flavor and size just add 20" -> the Ice Cream add-on.
    private const ADDONS = [
        'Extra Shot'    => 30,
        'Syrup'         => 10,
        'Ice Cream'     => 20,
        'Popping Boba'  => 15,
        'Rainbow Jelly' => 15,
    ];

    private const ADDON_RECIPES = [
        'Extra Shot'    => ['beans' => 18],
        'Syrup'         => ['vanilla' => 15],
        'Ice Cream'     => ['ice_cream' => 70],
        'Popping Boba'  => ['boba' => 30],
        'Rainbow Jelly' => ['jelly' => 30],
    ];

    private const ADDON_CATEGORIES = [
        'Extra Shot'    => ['Iced Coffee', 'Hot Coffee', 'Matcha'],
        'Syrup'         => ['Iced Coffee', 'Hot Coffee', 'Matcha'],
        'Ice Cream'     => ['Iced Coffee', 'Hot Coffee', 'Matcha', 'Strawberry', 'Blueberry', 'Pistachio', 'Choco', 'Flavored Soda'],
        'Popping Boba'  => ['Iced Coffee', 'Flavored Soda'],
        'Rainbow Jelly' => ['Flavored Soda'],
    ];

    /** How often each category sells (per item). Iced coffee is the bestseller. */
    private const CATEGORY_WEIGHT = [
        'Special Sundaes' => 5, 'Premium Sundaes' => 3, 'Cones' => 9, 'Frappe' => 4,
        'Non-Coffee Float' => 3, 'Iced Coffee' => 8, 'Hot Coffee' => 2.5, 'Matcha' => 5,
        'Strawberry' => 4, 'Blueberry' => 4, 'Pistachio' => 3, 'Choco' => 4,
        'Pearl Shakes' => 3.5, 'Flavored Soda' => 4,
    ];

    private const ITEM_WEIGHT = [
        'Caramel Macchiato' => 12, 'Spanish Latte' => 11, 'Latte' => 10, 'Americano' => 9,
        'Matcha Latte' => 9, 'Chocolate Sundae' => 8,
        'Biscoff Overload' => 2, 'Pistachio Jolt' => 2, 'Affogato' => 2,
    ];

    /** hour of day => relative order volume (shop opens 8:00, last orders ~21:00) */
    private const HOUR_WEIGHT = [8 => 4, 9 => 6, 10 => 8, 11 => 9, 12 => 10, 13 => 8, 14 => 7, 15 => 9, 16 => 10, 17 => 11, 18 => 10, 19 => 7, 20 => 4];

    /** ISO weekday (1=Mon) => traffic multiplier */
    private const DAY_FACTOR = [1 => 0.90, 2 => 0.90, 3 => 0.95, 4 => 1.00, 5 => 1.15, 6 => 1.35, 7 => 1.25];

    private const PERISHABLE  = ['milk', 'cream', 'ice_cream', 'strawberry', 'blueberry', 'mango', 'pistachio', 'pearls', 'boba', 'jelly', 'condensed', 'taro'];
    private const DAMAGEABLE  = ['cup16', 'lid', 'cone'];

    /** Demo states so the inventory + order screens show low / out-of-stock badges. */
    private const LOW_STOCK = [
        'Poblacion Main'  => ['biscoff', 'mango', 'white_choco'],
        'Mabini Branch'   => ['pistachio', 'lychee_syp'],
        'Uptown Terminal' => ['taro', 'boba'],
        'Airport Kiosk'   => ['cone', 'mango'],
    ];

    private const OUT_OF_STOCK = [
        'Riverside Stall' => ['biscoff'],
        'Airport Kiosk'   => ['pearls'],
    ];

    /* ===================================================================== */

    public function run(): void
    {
        mt_srand(20261012); // same data every run

        DB::transaction(function () {
            $branchIds     = $this->branches();
            $staffByBranch = $this->users($branchIds);
            $drinkTypeIds  = $this->drinkTypes();
            $ingredientIds = $this->ingredients();
            $menuIds       = $this->menu($drinkTypeIds, $ingredientIds);
            $addons        = $this->addons($ingredientIds, $drinkTypeIds);

            $this->simulate($branchIds, $staffByBranch, $menuIds, $addons, $ingredientIds);
        });
    }

    /* ------------------------------ master data ------------------------------ */

    private function branches(): array
    {
        foreach (array_keys(self::BRANCHES) as $name) {
            Branch::firstOrCreate(['name' => $name]);
        }

        return Branch::pluck('id', 'name')->all();
    }

    private function users(array $branchIds): array
    {
        User::updateOrCreate(['username' => 'Admin'], [
            'name' => 'Admin User', 'contact' => 'admin',
            'password' => Hash::make('1234'), 'role' => 'admin', 'branch_id' => null,
        ]);

        $flagship = array_key_first(self::BRANCHES);

        // The demo "Staff" login works at the flagship, and is part of its rota
        // so the Receipts page (which only shows your own orders) is full.
        $demo = User::updateOrCreate(['username' => 'Staff'], [
            'name' => 'Staff User', 'contact' => 'staff',
            'password' => Hash::make('1234'), 'role' => 'staff', 'branch_id' => $branchIds[$flagship],
        ]);

        $byBranch = [$flagship => [$demo->id]];

        foreach (self::STAFF_BY_BRANCH as $branch => $names) {
            foreach ($names as $name) {
                $handle = strtolower(strtok($name, ' '));

                $byBranch[$branch][] = User::updateOrCreate(['username' => ucfirst($handle)], [
                    'name' => $name, 'contact' => $handle,
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
        foreach (self::INGREDIENTS as $key => [$name, $unitType, $unit, $purchaseUnit, $factor, $cost, $daily]) {
            $ids[$key] = $this->put('ingredients', ['key' => $key], [
                'name'              => $name,
                'unit_type'         => $unitType,
                'unit'              => $unit,
                'purchase_unit'     => $purchaseUnit,
                'conversion_factor' => $factor,
                'cost'              => $cost,
                'reorder_level'     => max($factor, $this->ceilTo($daily * 3, $factor)),
                'is_active'         => true,
            ]);
        }

        return $ids;
    }

    private function menu(array $drinkTypeIds, array $ingredientIds): array
    {
        $recipes = $this->fullRecipes();
        $ids     = [];

        foreach (self::MENU as $name => [$price, $venti, $hasSizes, $hasTemp, $cat]) {
            $ids[$name] = $id = $this->put('menu_items', ['name' => $name], [
                'price'           => $price,
                'price_venti'     => $venti,
                'has_sizes'       => $hasSizes,
                'has_temperature' => $hasTemp,
                'drink_type_id'   => $drinkTypeIds[$cat] ?? null,
                'is_active'       => ! in_array($name, self::INACTIVE_ITEMS, true),
            ]);

            $this->recipe('menu_item_ingredient', 'menu_item_id', $id, $recipes[$name] ?? [], $ingredientIds);
        }

        return $ids;
    }

    private function addons(array $ingredientIds, array $drinkTypeIds): array
    {
        $out = [];
        $now = Carbon::now(self::TZ);

        foreach (self::ADDONS as $name => $price) {
            $id = $this->put('addons', ['name' => $name], ['price' => $price]);
            $out[$name] = ['id' => $id, 'price' => $price];

            $this->recipe('addon_ingredient', 'addon_id', $id, self::ADDON_RECIPES[$name] ?? [], $ingredientIds);

            DB::table('addon_drink_type')->where('addon_id', $id)->delete();
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

    /** RECIPES + auto-generated "Hot X" recipes (iced recipe without the ice). */
    private function fullRecipes(): array
    {
        $recipes = self::RECIPES;

        foreach (array_keys(self::MENU) as $name) {
            if (str_starts_with($name, 'Hot ') && isset(self::RECIPES[substr($name, 4)])) {
                $hot = self::RECIPES[substr($name, 4)];
                unset($hot['ice']);
                $recipes[$name] = $hot;
            }
        }

        return $recipes;
    }

    /* ------------------------------ simulation ------------------------------ */

    /**
     * Walks day by day, branch by branch: opening restocks, orders, spoilage.
     * Every change is logged in stock_movements, so SUM(change) == branch_inventory.stock.
     */
    private function simulate(array $branchIds, array $staffByBranch, array $menuIds, array $addons, array $ingredientIds): void
    {
        $now = Carbon::now(self::TZ);

        DB::table('order_item_addon')->delete();
        DB::table('order_items')->delete();
        DB::table('stock_movements')->delete();
        DB::table('orders')->delete();

        $recipes = $this->fullRecipes();

        // --- weighted menu pool (inactive items aren't sold) ---
        $pool = [];
        $poolTotal = 0;
        foreach (self::MENU as $name => $m) {
            if (in_array($name, self::INACTIVE_ITEMS, true)) {
                continue;
            }
            $weight = (int) round((self::ITEM_WEIGHT[$name] ?? self::CATEGORY_WEIGHT[$m[4]] ?? 3) * 10);
            $poolTotal += $weight;
            $pool[] = [$name, $poolTotal];
        }

        $pickItem = function () use ($pool, $poolTotal): string {
            $r = mt_rand(1, $poolTotal);
            foreach ($pool as [$name, $cumulative]) {
                if ($r <= $cumulative) {
                    return $name;
                }
            }

            return $pool[array_key_last($pool)][0];
        };

        $addonsByCategory = [];
        foreach (self::ADDON_CATEGORIES as $addon => $categories) {
            foreach ($categories as $category) {
                $addonsByCategory[$category][] = $addon;
            }
        }

        $makeLines = function () use ($pickItem, $addonsByCategory): array {
            $r     = mt_rand(1, 100);
            $count = $r <= 55 ? 1 : ($r <= 87 ? 2 : ($r <= 97 ? 3 : 4));
            $lines = [];

            for ($i = 0; $i < $count; $i++) {
                $name = $pickItem();
                [$price, $venti, $hasSizes, $hasTemp, $cat] = self::MENU[$name];

                $size = null;
                if ($hasSizes) {
                    if ($cat === 'Cones') {
                        $big  = mt_rand(1, 100) <= 30;
                        $size = $big ? 'giant' : 'small';
                    } else {
                        $big  = mt_rand(1, 100) <= 40;
                        $size = $big ? 'venti' : 'grande';
                    }
                    if ($big && $venti !== null) {
                        $price = $venti;
                    }
                }

                $q   = mt_rand(1, 100);
                $qty = $q <= 82 ? 1 : ($q <= 96 ? 2 : 3);

                $picked = [];
                $valid  = $addonsByCategory[$cat] ?? [];
                if ($valid && mt_rand(1, 100) <= 28) {
                    $picked[] = $valid[array_rand($valid)];
                    if (count($valid) > 1 && mt_rand(1, 100) <= 15) {
                        $second = $valid[array_rand($valid)];
                        if (! in_array($second, $picked, true)) {
                            $picked[] = $second;
                        }
                    }
                }

                $lines[] = [
                    'name'   => $name,
                    'qty'    => $qty,
                    'size'   => $size,
                    'price'  => $price,
                    'temp'   => $hasTemp ? (mt_rand(1, 100) <= 35 ? 'hot' : 'cold') : null,
                    'addons' => $picked,
                ];
            }

            return $lines;
        };

        // --- per-branch inventory parameters (in memory) ---
        $meta = [];
        foreach (self::INGREDIENTS as $key => $i) {
            $meta[$key] = ['f' => $i[4], 'daily' => $i[6]];
        }

        $reorder = $par = $stock = [];
        foreach ($branchIds as $branchName => $bId) {
            $scale = self::BRANCHES[$branchName];
            foreach ($meta as $key => $m) {
                $reorder[$bId][$key] = max($m['f'], $this->ceilTo($m['daily'] * 3 * $scale, $m['f']));
                $par[$bId][$key]     = max($reorder[$bId][$key] + $m['f'], $this->ceilTo($m['daily'] * 10 * $scale, $m['f']));
                $stock[$bId][$key]   = 0.0;
            }
        }

        $moves = [];

        $logMove = function (int $bId, string $key, ?int $orderId, ?int $userId, float $change, string $reason, Carbon $at) use (&$moves, $ingredientIds): void {
            $ts = $at->format(self::DT);
            $moves[] = [
                'ingredient_id' => $ingredientIds[$key],
                'order_id'      => $orderId,
                'user_id'       => $userId,
                'branch_id'     => $bId,
                'change'        => round($change, 2),
                'reason'        => $reason,
                'created_at'    => $ts,
                'updated_at'    => $ts,
            ];
        };

        $restock = function (int $bId, string $key, int $userId, Carbon $at) use (&$stock, $par, $meta, $logMove): void {
            $f      = $meta[$key]['f'];
            $units  = max(1, (int) ceil(($par[$bId][$key] - $stock[$bId][$key]) / $f));
            $amount = $units * $f;

            $stock[$bId][$key] += $amount;
            $logMove($bId, $key, null, $userId, $amount, 'restock', $at);
        };

        // --- build the timeline ---
        $events = [];

        for ($ago = self::HISTORY_DAYS; $ago >= 0; $ago--) {
            $day    = $now->copy()->startOfDay()->subDays($ago);
            $dayIdx = (int) floor($day->timestamp / 86400);
            $factor = self::DAY_FACTOR[$day->dayOfWeekIso] * (in_array($day->day, [15, 30], true) ? 1.2 : 1.0);

            foreach ($branchIds as $branchName => $bId) {
                $staff   = $staffByBranch[$branchName];
                $morning = $staff[$dayIdx % count($staff)];
                $evening = $staff[($dayIdx + 1) % count($staff)];

                $openAt = $day->copy()->setTime(7, mt_rand(10, 50));
                if ($openAt->lessThanOrEqualTo($now)) {
                    $events[] = ['type' => 'open', 'at' => $openAt, 'ts' => $openAt->timestamp, 'branch' => $bId, 'user' => $morning];
                }

                if (mt_rand(1, 100) <= 30) {
                    $wasteAt = $day->copy()->setTime(21, mt_rand(5, 45));
                    if ($wasteAt->lessThanOrEqualTo($now)) {
                        $events[] = ['type' => 'waste', 'at' => $wasteAt, 'ts' => $wasteAt->timestamp, 'branch' => $bId, 'user' => $evening];
                    }
                }

                $count = max(2, (int) round(self::BASE_ORDERS * self::BRANCHES[$branchName] * $factor * mt_rand(85, 115) / 100));

                foreach ($this->orderTimes($day, $now, $count, $ago) as $at) {
                    $r = mt_rand(1, 1000);
                    $events[] = [
                        'type'    => 'order',
                        'at'      => $at,
                        'ts'      => $at->timestamp,
                        'branch'  => $bId,
                        'user'    => $at->hour < 14 ? $morning : $evening,
                        'payment' => mt_rand(1, 100) <= 42 ? 'gcash' : 'cash',
                        'status'  => $r <= 940 ? 'completed' : ($r <= 975 ? 'refunded' : 'voided'),
                        'lines'   => $makeLines(),
                    ];
                }
            }
        }

        usort($events, fn ($a, $b) => $a['ts'] <=> $b['ts']);

        // --- play the timeline ---
        $orders = $items = $itemAddons = [];
        $orderId = 0;
        $itemId  = 0;
        $orderNo = 0;

        foreach ($events as $e) {
            $bId = $e['branch'];

            if ($e['type'] === 'open') {
                foreach ($meta as $key => $m) {
                    if ($stock[$bId][$key] <= $reorder[$bId][$key]) {
                        $restock($bId, $key, $e['user'], $e['at']);
                    }
                }
                continue;
            }

            if ($e['type'] === 'waste') {
                $keys = (array) array_rand(array_flip(self::PERISHABLE), mt_rand(1, 2));
                foreach ($keys as $key) {
                    $amount = round(min($stock[$bId][$key] * 0.5, $meta[$key]['f'] * mt_rand(2, 9) / 100), 2);
                    if ($amount >= 1) {
                        $stock[$bId][$key] -= $amount;
                        $logMove($bId, $key, null, $e['user'], -$amount, 'stock_out: ' . (mt_rand(1, 100) <= 70 ? 'spoilage' : 'expired'), $e['at']);
                    }
                }
                if (mt_rand(1, 100) <= 50) {
                    $key    = self::DAMAGEABLE[array_rand(self::DAMAGEABLE)];
                    $amount = (float) min((int) $stock[$bId][$key], mt_rand(1, 4));
                    if ($amount >= 1) {
                        $stock[$bId][$key] -= $amount;
                        $logMove($bId, $key, null, $e['user'], -$amount, 'stock_out: damaged', $e['at']);
                    }
                }
                continue;
            }

            // ----- order -----
            $orderId++;
            $orderNo++;
            $ts = $e['at']->format(self::DT);

            $orders[] = [
                'id'             => $orderId,
                'order_no'       => str_pad((string) $orderNo, 4, '0', STR_PAD_LEFT),
                'user_id'        => $e['user'],
                'branch_id'      => $bId,
                'payment_method' => $e['payment'],
                'gcash_ref'      => $e['payment'] === 'gcash' ? $this->gcashRef() : null,
                'status'         => $e['status'],
                'created_at'     => $ts,
                'updated_at'     => $ts,
            ];

            $need = [];
            foreach ($e['lines'] as $line) {
                $itemId++;
                $items[] = [
                    'id'           => $itemId,
                    'order_id'     => $orderId,
                    'menu_item_id' => $menuIds[$line['name']],
                    'quantity'     => $line['qty'],
                    'unit_price'   => $line['price'],
                    'size'         => $line['size'],
                    'temperature'  => $line['temp'],
                    'created_at'   => $ts,
                    'updated_at'   => $ts,
                ];

                foreach ($recipes[$line['name']] ?? [] as $key => $qty) {
                    $need[$key] = ($need[$key] ?? 0) + $qty * $line['qty'];
                }

                foreach ($line['addons'] as $addonName) {
                    $itemAddons[] = [
                        'order_item_id' => $itemId,
                        'addon_id'      => $this->addonIds[$addonName] ?? null,
                        'unit_price'    => self::ADDONS[$addonName],
                        'created_at'    => $ts,
                        'updated_at'    => $ts,
                    ];

                    foreach (self::ADDON_RECIPES[$addonName] ?? [] as $key => $qty) {
                        $need[$key] = ($need[$key] ?? 0) + $qty * $line['qty'];
                    }
                }
            }

            // Only completed orders consume stock (same as the live app).
            if ($e['status'] === 'completed') {
                foreach ($need as $key => $qty) {
                    while ($stock[$bId][$key] < $qty) {
                        // emergency delivery shortly before this order, so stock never goes negative
                        $restock($bId, $key, $e['user'], $e['at']->copy()->subMinutes(mt_rand(2, 20)));
                    }
                    $stock[$bId][$key] -= $qty;
                    $logMove($bId, $key, $orderId, null, -$qty, 'order', $e['at']);
                }
            }
        }

        // --- demo states: a few low / out-of-stock ingredients ---
        $yesterdayNight = $now->copy()->subDay()->setTime(22, 30);
        $adjust = function (string $branchName, string $key, float $target) use ($branchIds, $staffByBranch, &$stock, $logMove, $yesterdayNight): void {
            $bId = $branchIds[$branchName];
            if ($stock[$bId][$key] > $target) {
                $amount = round($stock[$bId][$key] - $target, 2);
                $stock[$bId][$key] -= $amount;
                $logMove($bId, $key, null, $staffByBranch[$branchName][0], -$amount, 'stock_out: inventory count correction', $yesterdayNight);
            }
        };

        foreach (self::LOW_STOCK as $branchName => $keys) {
            foreach ($keys as $key) {
                $adjust($branchName, $key, round($reorder[$branchIds[$branchName]][$key] * 0.55, 2));
            }
        }
        foreach (self::OUT_OF_STOCK as $branchName => $keys) {
            foreach ($keys as $key) {
                $adjust($branchName, $key, 0.0);
            }
        }

        // --- write everything (parents before children) ---
        $this->bulk('orders', $orders);
        $this->bulk('order_items', $items);
        $this->bulk('order_item_addon', $itemAddons);
        $this->bulk('stock_movements', $moves);

        $nowDb = $now->format(self::DT);
        foreach ($branchIds as $bId) {
            foreach ($meta as $key => $m) {
                DB::table('branch_inventory')->updateOrInsert(
                    ['branch_id' => $bId, 'ingredient_id' => $ingredientIds[$key]],
                    [
                        'stock'         => round($stock[$bId][$key], 2),
                        'reorder_level' => $reorder[$bId][$key],
                        'is_active'     => true,
                        'created_at'    => $nowDb,
                        'updated_at'    => $nowDb,
                    ]
                );
            }
        }

        // keep the legacy ingredients.stock column = total across branches
        foreach ($meta as $key => $m) {
            $total = 0;
            foreach ($branchIds as $bId) {
                $total += $stock[$bId][$key];
            }
            DB::table('ingredients')->where('id', $ingredientIds[$key])->update([
                'stock'      => round($total, 2),
                'updated_at' => $nowDb,
            ]);
        }

        $this->command?->info(sprintf(
            'Seeded %d orders, %d order items, %d add-ons and %d stock movements across %d branches.',
            count($orders), count($items), count($itemAddons), count($moves), count($branchIds)
        ));
    }

    /** Order timestamps for one branch/day. Today only includes times that already passed. */
    private function orderTimes(Carbon $day, Carbon $now, int $count, int $ago): array
    {
        $times = [];

        // Seeded before opening hours? Still give "today" a few fresh orders.
        if ($ago === 0 && $now->hour < 9) {
            $n = mt_rand(2, 5);
            for ($i = 0; $i < $n; $i++) {
                $times[] = $now->copy()->subMinutes(mt_rand(2, 55));
            }

            return $times;
        }

        $totalWeight = array_sum(self::HOUR_WEIGHT);

        for ($i = 0; $i < $count; $i++) {
            $r = mt_rand(1, $totalWeight);
            $hour = 20;
            foreach (self::HOUR_WEIGHT as $h => $w) {
                if (($r -= $w) <= 0) {
                    $hour = $h;
                    break;
                }
            }

            $t = $day->copy()->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
            if ($t->lessThanOrEqualTo($now)) {
                $times[] = $t;
            }
        }

        return $times;
    }

    /* -------------------------------- helpers -------------------------------- */

    /** @var array<string,int> addon name => id (filled by put() via addons()) */
    private array $addonIds = [];

    /** 13-digit GCash-style reference number (what staff type into the "Ref. No." box). */
    private function gcashRef(): string
    {
        return mt_rand(1000, 9999) . str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function ceilTo(float|int $value, float|int $step): float|int
    {
        return (int) ceil($value / $step) * $step;
    }

    private function bulk(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function put(string $table, array $match, array $values = []): int
    {
        $now = Carbon::now(self::TZ);
        $row = DB::table($table)->where($match)->first();

        if ($row) {
            DB::table($table)->where('id', $row->id)->update($values + ['updated_at' => $now]);
            $id = $row->id;
        } else {
            $id = DB::table($table)->insertGetId($match + $values + ['created_at' => $now, 'updated_at' => $now]);
        }

        if ($table === 'addons') {
            $this->addonIds[$match['name']] = $id;
        }

        return $id;
    }

    private function recipe(string $table, string $ownerCol, int $ownerId, array $recipe, array $ingredientIds): void
    {
        DB::table($table)->where($ownerCol, $ownerId)->delete();

        if (! $recipe) {
            return;
        }

        $now = Carbon::now(self::TZ);
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