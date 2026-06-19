<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::resource('menus', MenuController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('materials', RawMaterialController::class);
    Route::resource('purchase', PurchaseOrderController::class);
});

require __DIR__.'/settings.php';
require __DIR__.'/api.php';
