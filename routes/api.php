<?php

use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

// Gunakan middleware yang sesuai: 'auth' (untuk session) atau 'auth:sanctum' (untuk token)
Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function () {

    // Suppliers (Gunakan group jika ingin menambahkan middleware khusus per resource)
    Route::apiResource('suppliers', SupplierController::class);
    Route::post('/suppliers/bulk-delete', [SupplierController::class, 'bulkDestroy']);

    // Raw Materials
    Route::prefix('raw-materials')->group(function () {
        Route::post('bulk-delete', [RawMaterialController::class, 'bulkDestroy']);
        Route::get('{rawMaterial}/ledger', [RawMaterialController::class, 'ledger']);
    });
    Route::apiResource('raw-materials', RawMaterialController::class);

    // Purchase Orders
    Route::prefix('purchase-orders')->group(function () {
        Route::post('bulk-delete', [PurchaseOrderController::class, 'bulkDestroy']);
        Route::post('{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    });
    Route::apiResource('purchase-orders', PurchaseOrderController::class);

    // Menus
    Route::prefix('menus')->group(function () {
        Route::get('channels', [MenuController::class, 'channels']);
    });
    Route::apiResource('menus', MenuController::class);
});