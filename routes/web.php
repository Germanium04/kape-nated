<?php

use App\Support\DemoData;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', fn() => view('layouts.authentication.Login'))->name('authentication.login');

Route::get('/signup', function () {
    return view('layouts.authentication.Signup');
 })->name('authentication.signup');

Route::post('/signup', function () {
    return redirect()->route('authentication.login');
})->name('authentication.signup.submit');



// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {

    Route::redirect('/', '/admin/dashboard');

    Route::get('/dashboard', function () {
        return view('layouts.admin.Dashboard', [
            'orders'      => DemoData::orders(),
            'ingredients' => DemoData::ingredients(),
            'sales'       => DemoData::salesByDay(),
            'topItems'    => DemoData::topItems(),
        ]);
    })->name('dashboard');

    Route::get('/receipts', function () {
        return view('layouts.admin.Receipts', [
            'orders'   => DemoData::orders(),
            'branches' => DemoData::branches(),
        ]);
    })->name('receipts');

    Route::get('/sales', function () {
        return view('layouts.admin.Sales', [
            'sales'    => DemoData::salesByDay(),
            'topItems' => DemoData::topItems(),
            'orders'   => DemoData::orders(),
        ]);
    })->name('sales');

    Route::get('/menu', function () {
        return view('layouts.admin.Menu', [
            'menuItems'   => DemoData::menuItems(),
            'ingredients' => DemoData::ingredients(),
        ]);
    })->name('menu');

    Route::get('/inventory', function () {
        return view('layouts.admin.Inventory-admin', [
            'ingredients'  => DemoData::ingredients(),
            'recipes'      => DemoData::recipes(),
            'addonRecipes' => DemoData::addonRecipes(),
        ]);
    })->name('inventory');
});