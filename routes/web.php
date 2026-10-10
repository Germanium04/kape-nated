<?php

use App\Support\DemoData;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AdminController;
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
// ADMIN ROUTES  (role: admin) — unchanged, still on DemoData
// ============================================================

Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth', 'role:admin'])
        ->controller(AdminController::class)
        ->group(function () {

            Route::redirect('/', '/admin/dashboard');

            Route::get('/dashboard', 'dashboard')->name('dashboard');
            Route::get('/receipts', 'receipts')->name('receipts');
            Route::get('/sales', 'sales')->name('sales');
            Route::get('/menu', 'menu')->name('menu');
            Route::get('/inventory', 'inventory')->name('inventory');
        });

// ============================================================
// STAFF ROUTES  (role: staff) — real data, all in StaffController
// ============================================================

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth', 'role:staff'])
    ->controller(StaffController::class)
    ->group(function () {

        Route::redirect('/', '/staff/orders');

        // Orders: the till screen
        Route::get('/orders', 'orders')->name('orders');
        Route::post('/orders', 'storeOrder')->name('orders.store');

        // Inventory: stock levels + restock / actions
        Route::get('/inventory', 'inventory')->name('inventory');
        Route::post('/inventory/{ingredient}/restock', 'restock')->name('inventory.restock');
        Route::post('/inventory/action', 'handleInventoryAction')->name('inventory.action'); // <-- ADD THIS LINE
        Route::patch('/admin/ingredients/{ingredient}/toggle', [AdminController::class, 'toggleIngredient'])->name('admin.ingredients.toggle');
    });