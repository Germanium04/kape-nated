<?php

use App\Support\DemoData;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


// ============================================================
// PUBLIC / AUTHENTICATION ROUTES
// ============================================================

Route::middleware('guest')->group(function () {

    // Login page
    Route::get('/', [AuthController::class, 'showLogin'])
        ->name('authentication.login');

    // Login submission
    Route::post('/login', [AuthController::class, 'login'])
        ->name('authentication.login.submit');

    // Signup page
    Route::get('/signup', [AuthController::class, 'showSignup'])
        ->name('authentication.signup');

    // Signup submission
    Route::post('/signup', [AuthController::class, 'signup'])
        ->name('authentication.signup.submit');
});


// ============================================================
// LOGOUT
// ============================================================

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('authentication.logout');


// ============================================================
// ADMIN ROUTES
// ============================================================

Route::prefix('admin')
    ->name('admin.')
    ->middleware('auth')
    ->group(function () {

        Route::redirect('/', '/admin/dashboard');


        // Dashboard
        Route::get('/dashboard', function () {
            return view('layouts.admin.Dashboard', [
                'orders'      => DemoData::orders(),
                'ingredients' => DemoData::ingredients(),
                'sales'       => DemoData::salesByDay(),
                'topItems'    => DemoData::topItems(),
            ]);
        })->name('dashboard');


        // Receipts
        Route::get('/receipts', function () {
            return view('layouts.admin.Receipts', [
                'orders'   => DemoData::orders(),
                'branches' => DemoData::branches(),
            ]);
        })->name('receipts');


        // Sales
        Route::get('/sales', function () {
            return view('layouts.admin.Sales', [
                'sales'    => DemoData::salesByDay(),
                'topItems' => DemoData::topItems(),
                'orders'   => DemoData::orders(),
            ]);
        })->name('sales');


        // Menu
        Route::get('/menu', function () {
            return view('layouts.admin.Menu', [
                'menuItems'   => DemoData::menuItems(),
                'drinkTypes'  => DemoData::drinkTypes(),
                'ingredients' => DemoData::ingredients(),
            ]);
        })->name('menu');


        // Inventory
        Route::get('/inventory', function () {
            return view('layouts.admin.Inventory-admin', [
                'ingredients'  => DemoData::ingredients(),
                'branchWeights' => DemoData::branchWeights(),
                'recipes'      => DemoData::recipes(),
                'addonRecipes' => DemoData::addonRecipes(),
            ]);
        })->name('inventory');
    });


// ============================================================
// STAFF ROUTES
// ============================================================

Route::prefix('staff')
    ->name('staff.')
    ->middleware('auth')
    ->group(function () {

        Route::redirect('/', '/staff/dashboard');


        // Dashboard
        Route::get('/dashboard', function () {
            return view('layouts.staff.Dashboard', [
                'orders'      => DemoData::orders(),
                'ingredients' => DemoData::ingredients(),
                'sales'       => DemoData::salesByDay(),
                'topItems'    => DemoData::topItems(),
            ]);
        })->name('dashboard');


        // Orders
        Route::get('/orders', function () {
            return view('layouts.staff.Order', [
                'menuItems'      => DemoData::menuItems(),
                'customizations' => DemoData::customizations(),
                'ingredients'    => DemoData::ingredients(),
            ]);
        })->name('orders');


        // Inventory
        Route::get('/inventory', function () {
            return view('layouts.staff.Inventory-staff', [
                'ingredients'  => DemoData::ingredients(),
                'recipes'      => DemoData::recipes(),
                'addonRecipes' => DemoData::addonRecipes(),
            ]);
        })->name('inventory');
    });