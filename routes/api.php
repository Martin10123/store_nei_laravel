<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCustomerController;
use App\Http\Controllers\DailyCloseController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('select 1');
    Redis::connection()->ping();

    return ['ok' => true];
});

Route::get('/presets', [AuthController::class, 'presets']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/business', [BusinessController::class, 'update']);
    Route::get('/units', [ProductController::class, 'units']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index']);
    Route::post('/inventory-movements', [InventoryMovementController::class, 'store']);
    Route::get('/credit-customers', [CreditCustomerController::class, 'index']);
    Route::post('/credit-customers', [CreditCustomerController::class, 'store']);
    Route::get('/credit-customers/{creditCustomer}/movements', [CreditCustomerController::class, 'movements']);
    Route::post('/credit-customers/{creditCustomer}/payments', [CreditCustomerController::class, 'pay']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/daily-closes', [DailyCloseController::class, 'index']);
    Route::get('/daily-closes/preview', [DailyCloseController::class, 'preview']);
    Route::post('/daily-closes', [DailyCloseController::class, 'store']);
});
