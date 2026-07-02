<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountMappingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OverheadCostController;
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
    Route::get('raw-materials/options', [RawMaterialController::class, 'options']);
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
        Route::get('overhead-sync-status', [MenuController::class, 'checkOverheadSync']);
        Route::post('overhead-sync', [MenuController::class, 'syncOverhead']);
    });
    Route::apiResource('menus', MenuController::class);

    // Overhead
    Route::apiResource('overhead-costs', OverheadCostController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::prefix('finance')->group(function() {
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('account-mapping', AccountMappingController::class);
        Route::apiResource('journal-entry', JournalEntryController::class);
    });

    //Order
    Route::prefix('pos')->group(function () {
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'getOrdersData']);
        Route::get('/orders-unpaid', [OrderController::class, 'getUnpaidOrders']);
        Route::get('/invoices-paid', [OrderController::class, 'getPaidInvoices']);
    });
    
});