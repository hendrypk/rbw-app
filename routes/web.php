<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountMappingController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OverheadCostController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth', 'verified'])->group(function () {
Route::inertia('/', 'Dashboard')->name('home');
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::resource('menus', MenuController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('materials', RawMaterialController::class);
    Route::resource('purchase', PurchaseOrderController::class);
    Route::resource('overhead', OverheadCostController::class);
    Route::resource('account', AccountController::class);
    Route::resource('account-mapping', AccountMappingController::class);
    Route::resource('journal', JournalEntryController::class);
;});

require __DIR__.'/settings.php';
require __DIR__.'/api.php';
