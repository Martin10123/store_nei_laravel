<?php

use App\Models\BusinessTypePreset;
use App\Models\CreditCustomer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('records stock, a credit sale, a payment, and the daily close for one store', function () {
    expect(config('database.default'))->toBe('sqlite');

    $this->seed();

    $preset = BusinessTypePreset::query()->where('name', 'corner_store')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('abbreviation', 'und')->firstOrFail();

    $registration = $this->postJson('/api/register', [
        'full_name' => 'Ana',
        'email' => 'ana-phase1@store.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'business_name' => 'Ana Store',
        'business_type_preset_id' => $preset->id,
    ])->assertCreated();

    $token = $registration->json('token');

    $categoryId = $this->withToken($token)->getJson('/api/categories')->json('0.id');

    $productId = $this->withToken($token)->postJson('/api/products', [
        'name' => 'Rice',
        'category_id' => $categoryId,
        'unit_of_measure_id' => $unit->id,
        'cost_price' => 2000,
        'sale_price' => 3000,
        'current_stock' => 10,
    ])->assertCreated()->json('id');

    $this->withToken($token)->postJson('/api/inventory-movements', [
        'product_id' => $productId,
        'movement_type' => 'in',
        'quantity' => 5,
        'reason' => 'compra',
    ])->assertCreated();

    expect((float) Product::query()->find($productId)->current_stock)->toBe(15.0);

    $customerId = $this->withToken($token)->postJson('/api/credit-customers', [
        'full_name' => 'Pedro',
        'phone' => '3000000000',
        'credit_limit' => 20000,
    ])->assertCreated()->json('id');

    $this->withToken($token)->postJson('/api/sales', [
        'payment_method' => 'credit',
        'credit_customer_id' => $customerId,
        'lines' => [
            ['product_id' => $productId, 'quantity' => 2],
        ],
    ])->assertCreated()->assertJsonPath('total', '6000.00');

    expect((float) Product::query()->find($productId)->current_stock)->toBe(13.0);
    expect((float) CreditCustomer::query()->find($customerId)->current_balance)->toBe(6000.0);

    $this->withToken($token)->postJson("/api/credit-customers/{$customerId}/payments", [
        'amount' => 1000,
    ])->assertCreated();

    expect((float) CreditCustomer::query()->find($customerId)->current_balance)->toBe(5000.0);

    $closedOn = now('America/Bogota')->toDateString();

    $this->withToken($token)->postJson('/api/daily-closes', [
        'closed_on' => $closedOn,
    ])->assertCreated()
        ->assertJsonPath('total_sold', '6000.00')
        ->assertJsonPath('total_credit', '6000.00')
        ->assertJsonPath('top_product_id', $productId);

    $this->withToken($token)->postJson('/api/daily-closes', [
        'closed_on' => $closedOn,
    ])->assertUnprocessable();

    $this->app['auth']->forgetGuards();

    $other = $this->postJson('/api/register', [
        'full_name' => 'Luis',
        'email' => 'luis-phase1@store.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'business_name' => 'Luis Store',
        'business_type_preset_id' => $preset->id,
    ])->assertCreated();

    $this->withToken($other->json('token'))->getJson('/api/sales')->assertOk()->assertJsonCount(0);
    $this->withToken($other->json('token'))->getJson('/api/credit-customers')->assertOk()->assertJsonCount(0);

    expect(Sale::query()->withoutGlobalScopes()->count())->toBe(1);
});
