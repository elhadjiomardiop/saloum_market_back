<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\VendorProductController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API Laravel fonctionne correctement',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'API Laravel fonctionne correctement',
    ]);
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/stores/{username}', [StoreController::class, 'publicStore']);
Route::get('/stores/{username}/products', [ProductController::class, 'storeProducts']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth.token')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth.token')->group(function () {
    Route::get('/admin/dashboard', [StoreController::class, 'adminDashboard'])->middleware('role:admin');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->middleware('role:admin');
    Route::get('/admin/stores', [StoreController::class, 'adminStores'])->middleware('role:admin');
    Route::patch('/admin/stores/{store}/status', [StoreController::class, 'updateStoreStatus'])->middleware('role:admin');
    Route::patch('/admin/stores/{store}/active', [StoreController::class, 'toggleStoreActive'])->middleware('role:admin');
    Route::get('/admin/coupons', [CouponController::class, 'index'])->middleware('role:admin');
    Route::post('/admin/coupons', [CouponController::class, 'store'])->middleware('role:admin');
    Route::delete('/admin/coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('role:admin');

    Route::get('/vendor/dashboard', [StoreController::class, 'vendorDashboard'])->middleware('role:vendor');
    Route::get('/vendor/store', [StoreController::class, 'myStore'])->middleware('role:vendor');
    Route::post('/vendor/store', [StoreController::class, 'submitVendorStore'])->middleware('role:vendor');
    Route::get('/vendor/products', [VendorProductController::class, 'index'])->middleware('role:vendor');
    Route::post('/vendor/products', [VendorProductController::class, 'store'])->middleware('role:vendor');
    Route::patch('/vendor/products/{product}', [VendorProductController::class, 'update'])->middleware('role:vendor');
    Route::delete('/vendor/products/{product}', [VendorProductController::class, 'destroy'])->middleware('role:vendor');
    Route::patch('/vendor/products/{product}/active', [VendorProductController::class, 'toggleActive'])->middleware('role:vendor');
    Route::get('/vendor/orders', [OrderController::class, 'vendorOrders'])->middleware('role:vendor');
    Route::patch('/vendor/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('role:vendor');

    Route::get('/client/profile', function () {
        return response()->json([
            'message' => 'Client access granted.',
        ]);
    })->middleware('role:client');

    Route::get('/orders', [OrderController::class, 'clientOrders'])->middleware('role:client');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('role:client');
});
