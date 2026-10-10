<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// ============================================================
// PUBLIC / AUTHENTICATION ROUTES
// ============================================================

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('authentication.login');
    Route::post('/login', [AuthController::class, 'login'])->name('authentication.login.submit');
    Route::get('/signup', [AuthController::class, 'showSignup'])->name('authentication.signup');
    Route::post('/signup', [AuthController::class, 'signup'])->name('authentication.signup.submit');
});

// ============================================================
// LOGOUT
// ============================================================

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('authentication.logout');

// ============================================================
// ADMIN ROUTES (role: admin)
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

        // Database Persistence AJAX Endpoints
        Route::post('/drinks/store', 'storeDrink')->name('drinks.store');
        Route::patch('/drinks/{menuItem}/toggle', 'toggleDrink')->name('drinks.toggle');
        Route::post('/ingredients/store', 'storeIngredient')->name('ingredients.store');
        Route::patch('/ingredients/{ingredient}/toggle', 'toggleIngredient')->name('ingredients.toggle');
        Route::post('/inventory/operation', 'processStockOperation')->name('inventory.operation');
    });

// ============================================================
// STAFF ROUTES (role: staff)
// ============================================================

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth', 'role:staff'])
    ->controller(StaffController::class)
    ->group(function () {

        Route::redirect('/', '/staff/orders');

        Route::get('/orders', 'orders')->name('orders');
        Route::post('/orders', 'storeOrder')->name('orders.store');

        Route::get('/inventory', 'inventory')->name('inventory');
        Route::post('/inventory/{ingredient}/restock', 'restock')->name('inventory.restock');
        Route::post('/inventory/action', 'handleInventoryAction')->name('inventory.action');
    });