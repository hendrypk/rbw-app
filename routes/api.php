<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountMappingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerLoyaltyController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OverheadCostController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\QrisController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\VoucherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test-doku-token', [QrisController::class, 'testGetToken']);

Route::prefix('api/v1/user')->group(function () {
    
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/login', [CustomerAuthController::class, 'login']);
    

    Route::middleware(['auth:sanctum,customer'])->group(function () {
        Route::get('/check', function (Request $request) {
            return response()->json([
                'authenticated' => true, 
                'user' => $request->user()
            ]);
        });
        Route::get('/profile', [CustomerAuthController::class, 'profile']);
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/menus', [MenuController::class, 'userIndex']);
        Route::get('/voucher/index', [VoucherController::class, 'index']);
        Route::post('/voucher/validate', [VoucherController::class, 'validateVoucher']);
        Route::get('/menus', [MenuController::class, 'userIndex']);
        Route::post('/checkout', [OrderController::class, 'userCheckout']);
        Route::get('/my-orders', [OrderController::class, 'getUserOrders']);
        Route::get('/my-order/{orderNumber}', [OrderController::class, 'getOrderDetail']);
        Route::post('/payment/qris/generate', [QrisController::class, 'generate']);
        Route::get('/payment/qris/debug', [QrisController::class, 'debugGenerate']);
        Route::post('/payment/qris/check-status', [QrisController::class, 'checkStatus']);
        Route::get('/loyalty-profile', [CustomerLoyaltyController::class, 'myLoyaltyProfile']);
        Route::get('/leaderboard', [CustomerLoyaltyController::class, 'leaderboard']);
        Route::get('/redemptions', [CustomerLoyaltyController::class, 'availableRedemptions']);
        Route::post('/redeem', [CustomerLoyaltyController::class, 'redeemVoucher']);
    });
});

Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function () {

    Route::apiResource('suppliers', SupplierController::class);
    Route::post('/suppliers/bulk-delete', [SupplierController::class, 'bulkDestroy']);

    Route::prefix('customers')->group(function () {
        Route::post('bulk-delete', [CustomerController::class, 'bulkDestroy']);
    });
    Route::apiResource('customers', CustomerController::class);


    Route::apiResource('vouchers', VoucherController::class);
        Route::prefix('vouchers')->group(function () {
        Route::post('bulk-delete', [VoucherController::class, 'bulkDestroy']);
        Route::post('validate', [VoucherController::class, 'validateVoucher']);
    });
    Route::apiResource('vouchers', VoucherController::class);


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
    Route::prefix('purchase-orders')->group(function () {
        Route::post('{id}/payments', [PurchaseOrderController::class, 'payOrder'])->name('po.payOrder');
    });

    // Menus
    Route::prefix('menus')->group(function () {
        Route::get('channels', [MenuController::class, 'channels']);
        Route::get('overhead-sync-status', [MenuController::class, 'checkOverheadSync']);
        Route::post('overhead-sync', [MenuController::class, 'syncOverhead']);
        Route::get('recipe-sync-status', [MenuController::class, 'checkRecipeSync']);
        Route::post('sync-recipes', [MenuController::class, 'syncRecipes']);
    });
    Route::apiResource('menus', MenuController::class);

    // Overhead
    Route::apiResource('overhead-costs', OverheadCostController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::prefix('finance')->group(function() {
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('account-mappings', AccountMappingController::class);
        Route::apiResource('journal-entry', JournalEntryController::class);
    });

    //Order
    Route::prefix('pos')->group(function () {
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'getOrdersData']);
        Route::post('/orders/{id}/mark-paid', [OrderController::class, 'markOrderAsPaid']);
        Route::get('/orders-unpaid', [OrderController::class, 'getUnpaidOrders']);
        Route::get('/invoices-paid', [OrderController::class, 'getPaidInvoices']);
    });
    
    //qris
    Route::prefix('payment')->group(function () {
        Route::prefix('qris')->group(function () {
            Route::post('/generate', [QrisController::class, 'generate']);
            Route::post('/check-status', [QrisController::class, 'checkStatus']);
        });
    });

});