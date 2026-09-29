<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessBranchController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCustomerController;
use App\Http\Controllers\DailyCloseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\EndCustomerController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\PriceQuoteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WhatsappMessageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('select 1');
    Redis::connection()->ping();

    return ['ok' => true];
});

Route::get('/public/{slug}/products', [PublicCatalogController::class, 'products'])
    ->middleware('throttle:public-catalog');

Route::get('/presets', [AuthController::class, 'presets']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
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
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts/refresh', [AlertController::class, 'refresh']);
    Route::patch('/alerts/{alert}', [AlertController::class, 'update']);
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/supplier-prices', [SupplierController::class, 'prices']);
    Route::post('/supplier-prices', [SupplierController::class, 'storePrice']);
    Route::get('/price-quotes', [PriceQuoteController::class, 'index']);
    Route::post('/price-quotes', [PriceQuoteController::class, 'store']);
    Route::post('/price-quotes/{priceQuote}/assign', [PriceQuoteController::class, 'assign']);
    Route::get('/end-customers', [EndCustomerController::class, 'index']);
    Route::post('/end-customers', [EndCustomerController::class, 'store']);
    Route::get('/delivery-orders', [DeliveryOrderController::class, 'index']);
    Route::post('/delivery-orders', [DeliveryOrderController::class, 'store']);
    Route::post('/delivery-orders/{deliveryOrder}/confirm', [DeliveryOrderController::class, 'confirm']);
    Route::post('/delivery-orders/{deliveryOrder}/deliver', [DeliveryOrderController::class, 'deliver']);
    Route::post('/delivery-orders/{deliveryOrder}/cancel', [DeliveryOrderController::class, 'cancel']);
    Route::get('/whatsapp/messages', [WhatsappMessageController::class, 'index']);
    Route::post('/whatsapp/messages', [WhatsappMessageController::class, 'store'])
        ->middleware('throttle:whatsapp');
    Route::get('/branches', [BusinessBranchController::class, 'index']);
    Route::post('/branches', [BusinessBranchController::class, 'store']);
    Route::patch('/branches/{businessBranch}', [BusinessBranchController::class, 'update']);
});
