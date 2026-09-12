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
use App\Http\Controllers\Api\CashierShiftController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\PosController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use SebastianBergmann\CodeCoverage\Report\Xml\Report;

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
    // 1. FINANCE / BACK OFFICE ONLY
    // Route::middleware(['can:manage-finance'])->prefix('finance')->group(function() {
    //     Route::apiResource('accounts', AccountController::class);
    //     Route::apiResource('account-mappings', AccountMappingController::class);
    //     Route::apiResource('journal-entry', JournalEntryController::class);
    // });

    // Dashboard & Outlet List
    Route::get('/summary', [ReportController::class, 'getSummary']);
    Route::get('/outlets', [OutletController::class, 'index']);
});

Route::middleware(['auth', 'verified', 'resolve.outlet'])->prefix('api')->name('api.')->group(function () {
        // 1. FINANCE / BACK OFFICE ONLY
    Route::prefix('finance')->group(function() {
        Route::post('accounts/opening-balances', [AccountController::class, 'updateOpeningBalances']);
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('account-mappings', AccountMappingController::class);
        Route::apiResource('journal-entry', JournalEntryController::class);
    });

    // ----------------------------------------------------
    // 2. MASTER DATA & OPERATIONAL
    // ----------------------------------------------------
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('vouchers', VoucherController::class);
    Route::post('/raw-materials/{rawMaterial}/adjust-stock', [RawMaterialController::class, 'adjustStock']);
    Route::get('/raw-materials/{rawMaterial}/ledger', [App\Http\Controllers\Api\RawMaterialController::class, 'ledger']);
    Route::get('raw-materials/options', [RawMaterialController::class, 'options']);
    Route::apiResource('raw-materials', RawMaterialController::class);
    Route::post('purchase-orders/{id}/return', [PurchaseOrderController::class, 'purchaseReturn']);
    Route::post('purchase-orders/{id}/pay-order', [PurchaseOrderController::class, 'payOrder']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::get('menus/overhead-sync-status', [MenuController::class, 'checkOverheadSync']);
    Route::get('menus/recipe-sync-status', [MenuController::class, 'checkRecipeSync']);
    Route::post('menus/overhead-sync', [MenuController::class, 'syncOverhead']);
    Route::post('menus/sync-recipes', [MenuController::class, 'syncRecipes']);
    Route::post('menus/bulk-destroy', [MenuController::class, 'bulkDestroy']);
    Route::apiResource('menus', MenuController::class);
    Route::apiResource('overhead-costs', OverheadCostController::class);
    Route::apiResource('categories', CategoryController::class);

    // ----------------------------------------------------
    // 3. POS API TRANSACTIONS (Tetap butuh resolve.outlet)
    // ----------------------------------------------------
    Route::prefix('pos')->group(function () {
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'getOrdersData']);
        Route::post('/orders/{id}/mark-paid', [OrderController::class, 'markOrderAsPaid']);
        Route::post('/orders/{order}/pay', [OrderController::class, 'payOrder']);
        Route::get('/orders-unpaid', [OrderController::class, 'getUnpaidOrders']);
        Route::get('/invoices-paid', [OrderController::class, 'getPaidInvoices']);
        Route::get('/shifts/active', [CashierShiftController::class, 'checkActiveShift']);
        Route::post('/shifts/open', [CashierShiftController::class, 'openShift']);
        Route::post('/shifts/{shift}/close', [CashierShiftController::class, 'closeShift']);
        Route::get('/dashboard/summary', [OrderController::class, 'getSummary']);
    });
    
    // QRIS Payment
    Route::prefix('payment/qris')->group(function () {
        Route::post('/generate', [QrisController::class, 'generate']);
        Route::post('/check-status', [QrisController::class, 'checkStatus']);
    });
});