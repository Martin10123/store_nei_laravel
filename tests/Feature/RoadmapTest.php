<?php

use App\Models\Alert;
use App\Models\BusinessTypePreset;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockReservation;
use App\Models\UnitOfMeasure;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generates store alerts once and keeps them private', function () {
    expect(config('database.default'))->toBe('sqlite');

    $this->seed();

    $token = registerTendero($this, 'ana-alerts@store.test', 'Ana');
    $rice = createShelfProduct($this, $token, [
        'name' => 'Arroz',
        'current_stock' => 4,
        'minimum_stock' => 5,
    ]);
    createShelfProduct($this, $token, [
        'name' => 'Leche',
        'current_stock' => 5,
        'minimum_stock' => 0,
        'expiration_date' => Carbon::now('America/Bogota')->addDays(2)->toDateString(),
    ]);
    createShelfProduct($this, $token, [
        'name' => 'Azucar',
        'current_stock' => 5,
        'minimum_stock' => 0,
        'expiration_date' => Carbon::now('America/Bogota')->addDays(20)->toDateString(),
    ]);

    $yesterday = Carbon::now('America/Bogota')->subDay()->toDateString();
    $tomorrow = Carbon::now('America/Bogota')->addDay()->toDateString();
    $pedro = creditCustomer($this, $token, 'Pedro');
    $ana = creditCustomer($this, $token, 'Ana Cliente');
    $carlos = creditCustomer($this, $token, 'Carlos');

    creditSale($this, $token, $pedro, $rice, $yesterday);
    creditSale($this, $token, $ana, $rice, $tomorrow);
    creditSale($this, $token, $carlos, $rice, $yesterday);
    $this->withToken($token)->postJson("/api/credit-customers/{$carlos}/payments", [
        'amount' => 3000,
    ])->assertCreated();

    $closed = $this->withToken($token)->postJson('/api/daily-closes', [
        'closed_on' => Carbon::now('America/Bogota')->toDateString(),
    ])->assertCreated();

    expect($closed->json('ai_summary'))
        ->toContain('Arroz')
        ->toContain('fiado')
        ->toContain('$9.000');

    $first = $this->withToken($token)->postJson('/api/alerts/refresh')->assertOk();
    expect($first->json())->toHaveCount(3);
    expect(collect($first->json())->pluck('alert_type')->sort()->values()->all())
        ->toBe(['expiration', 'low_stock', 'overdue_credit']);
    expect(collect($first->json())->pluck('credit_customer_id')->filter()->values()->all())->toBe([$pedro]);

    $this->withToken($token)->postJson('/api/alerts/refresh')->assertOk()->assertJsonCount(3);
    expect(Alert::query()->withoutGlobalScopes()->count())->toBe(3);

    $lowStockId = collect($first->json())->firstWhere('alert_type', 'low_stock')['id'];
    $this->withToken($token)->patchJson("/api/alerts/{$lowStockId}")
        ->assertOk()
        ->assertJsonPath('is_resolved', true);

    $this->app['auth']->forgetGuards();
    $other = registerTendero($this, 'luis-alerts@store.test', 'Luis');
    $this->withToken($other)->getJson('/api/alerts')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->patchJson("/api/alerts/{$lowStockId}")->assertNotFound();
});

test('keeps supplier prices and manual quotes inside one store', function () {
    expect(config('database.default'))->toBe('sqlite');

    $this->seed();

    $token = registerTendero($this, 'ana-suppliers@store.test', 'Ana');
    $product = createShelfProduct($this, $token, ['name' => 'Arroz', 'cost_price' => 2000]);
    $supplier = $this->withToken($token)->postJson('/api/suppliers', [
        'name' => 'Mayorista Centro',
        'contact_name' => 'Lucia',
        'phone' => '3010000000',
    ])->assertCreated()->json('id');

    $this->withToken($token)->postJson('/api/supplier-prices', [
        'supplier_id' => $supplier,
        'product_id' => $product,
        'price' => 1000,
    ])->assertCreated()->assertJsonPath('source', 'manual');

    $this->withToken($token)->postJson('/api/supplier-prices', [
        'supplier_id' => $supplier,
        'product_id' => $product,
        'price' => 1500,
    ])->assertCreated();

    $this->withToken($token)->getJson('/api/supplier-prices')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.price', '1500.00');

    $quoteId = $this->withToken($token)->postJson('/api/price-quotes', [
        'found_name' => 'Arroz extra',
        'found_price' => 1800,
        'source_name' => 'Plaza',
    ])->assertCreated()->json('id');

    $this->withToken($token)->postJson("/api/price-quotes/{$quoteId}/assign", [
        'product_id' => $product,
    ])->assertOk()->assertJsonPath('product_id', $product);

    $this->app['auth']->forgetGuards();
    $other = registerTendero($this, 'luis-suppliers@store.test', 'Luis');
    $otherProduct = createShelfProduct($this, $other, ['name' => 'Frijol']);

    $this->withToken($other)->getJson('/api/suppliers')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->getJson('/api/supplier-prices')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->getJson('/api/price-quotes')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->postJson('/api/supplier-prices', [
        'supplier_id' => $supplier,
        'product_id' => $otherProduct,
        'price' => 500,
    ])->assertUnprocessable();
    $this->withToken($other)->postJson("/api/price-quotes/{$quoteId}/assign", [
        'product_id' => $otherProduct,
    ])->assertNotFound();
});

test('reserves delivery stock and sells it once when the order is delivered', function () {
    expect(config('database.default'))->toBe('sqlite');

    $this->seed();

    $token = registerTendero($this, 'ana-orders@store.test', 'Ana');
    $this->withToken($token)->patchJson('/api/business', [
        'public_slug' => 'ana-catalog',
    ])->assertOk()->assertJsonPath('public_slug', 'ana-catalog');

    $product = createShelfProduct($this, $token, [
        'name' => 'Arroz',
        'current_stock' => 10,
        'sale_price' => 3000,
    ]);
    $customer = $this->withToken($token)->postJson('/api/end-customers', [
        'name' => 'Marta',
        'phone' => '3020000000',
        'address' => 'Calle 1',
    ])->assertCreated()->json('id');

    $fullOrder = deliveryOrder($this, $token, $customer, $product, 10);
    $this->withToken($token)->postJson("/api/delivery-orders/{$fullOrder}/confirm")->assertOk()->assertJsonPath('status', 'confirmed');
    expect(stockOf($product))->toBe(10.0);
    expect(StockReservation::query()->where('is_released', false)->count())->toBe(1);

    $hidden = $this->getJson('/api/public/ana-catalog/products')->assertOk();
    expect(collect($hidden->json('products'))->pluck('name'))->toContain('Arroz');
    expect($hidden->json('products.0.in_stock'))->toBeFalse();
    expect($hidden->json('products.0'))->not->toHaveKey('current_stock')->not->toHaveKey('cost_price');

    $this->withToken($token)->postJson('/api/sales', [
        'payment_method' => 'cash',
        'lines' => [['product_id' => $product, 'quantity' => 1]],
    ])->assertUnprocessable();
    expect(stockOf($product))->toBe(10.0);

    $this->withToken($token)->postJson("/api/delivery-orders/{$fullOrder}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
    expect(stockOf($product))->toBe(10.0);
    expect(StockReservation::query()->where('is_released', false)->count())->toBe(0);
    expect($this->getJson('/api/public/ana-catalog/products')->json('products.0.in_stock'))->toBeTrue();

    $partial = deliveryOrder($this, $token, $customer, $product, 4);
    $this->withToken($token)->postJson("/api/delivery-orders/{$partial}/confirm")->assertOk();
    $this->withToken($token)->postJson('/api/sales', [
        'payment_method' => 'cash',
        'lines' => [['product_id' => $product, 'quantity' => 7]],
    ])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/sales', [
        'payment_method' => 'cash',
        'lines' => [['product_id' => $product, 'quantity' => 6]],
    ])->assertCreated();
    expect(stockOf($product))->toBe(4.0);

    $delivered = $this->withToken($token)->postJson("/api/delivery-orders/{$partial}/deliver")->assertOk();
    expect($delivered->json('status'))->toBe('delivered');
    expect($delivered->json('sale_id'))->not->toBeNull();
    expect(stockOf($product))->toBe(0.0);
    expect(StockReservation::query()->where('is_released', false)->count())->toBe(0);
    expect(Sale::query()->withoutGlobalScopes()->count())->toBe(2);

    $this->withToken($token)->postJson("/api/delivery-orders/{$partial}/deliver")->assertUnprocessable();
    expect(stockOf($product))->toBe(0.0);

    $this->getJson('/api/public/missing-catalog/products')->assertNotFound();

    $this->app['auth']->forgetGuards();
    $other = registerTendero($this, 'luis-orders@store.test', 'Luis');
    createShelfProduct($this, $other, ['name' => 'Secreto']);
    $catalog = $this->getJson('/api/public/ana-catalog/products')->assertOk();
    expect(collect($catalog->json('products'))->pluck('name'))->toContain('Arroz')->not->toContain('Secreto');
    $this->withToken($other)->getJson('/api/delivery-orders')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->postJson("/api/delivery-orders/{$partial}/confirm")->assertNotFound();
});

test('answers whatsapp queries and records an explicit sale for the current store', function () {
    expect(config('database.default'))->toBe('sqlite');

    $this->seed();

    $token = registerTendero($this, 'ana-wa@store.test', 'Ana');
    $product = createShelfProduct($this, $token, [
        'name' => 'Arroz',
        'current_stock' => 5,
        'sale_price' => 3000,
    ]);
    $pedro = creditCustomer($this, $token, 'Pedro');
    creditSale($this, $token, $pedro, $product, Carbon::now('America/Bogota')->toDateString());
    expect(stockOf($product))->toBe(4.0);

    $sales = $this->withToken($token)->postJson('/api/whatsapp/messages', [
        'phone_number' => '3001111111',
        'body' => 'ventas de hoy',
    ])->assertCreated();
    expect($sales->json('reply'))->toContain('$3.000');
    expect($sales->json('inbound.direction'))->toBe('inbound');
    expect($sales->json('outbound.direction'))->toBe('outbound');
    expect($sales->json('inbound.detected_intent'))->toBe('today_sales');

    $balance = $this->withToken($token)->postJson('/api/whatsapp/messages', [
        'phone_number' => '3001111111',
        'body' => 'saldo de Pedro',
    ])->assertCreated();
    expect($balance->json('reply'))->toContain('Pedro')->toContain('$3.000');

    $sold = $this->withToken($token)->postJson('/api/whatsapp/messages', [
        'phone_number' => '3001111111',
        'body' => 'vender 1 arroz',
    ])->assertCreated();
    expect($sold->json('inbound.detected_intent'))->toBe('record_sale');
    expect($sold->json('reply'))->toContain('Listo');
    expect(stockOf($product))->toBe(3.0);

    $blocked = $this->withToken($token)->postJson('/api/whatsapp/messages', [
        'phone_number' => '3001111111',
        'body' => 'vender 9 arroz',
    ])->assertCreated();
    expect($blocked->json('reply'))->toContain('No hay suficiente stock');
    expect(stockOf($product))->toBe(3.0);

    $this->withToken($token)->postJson('/api/whatsapp/messages', [
        'phone_number' => '3001111111',
        'body' => 'hola',
    ])->assertCreated()->assertJsonPath('inbound.detected_intent', 'unknown');
    expect(stockOf($product))->toBe(3.0);
    expect(Sale::query()->withoutGlobalScopes()->count())->toBe(2);

    $branch = $this->withToken($token)->postJson('/api/branches', [
        'name' => 'Centro',
    ])->assertCreated()->json('id');

    $this->app['auth']->forgetGuards();
    $other = registerTendero($this, 'luis-wa@store.test', 'Luis');
    $this->withToken($other)->getJson('/api/whatsapp/messages')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->getJson('/api/branches')->assertOk()->assertJsonCount(0);
    $this->withToken($other)->patchJson("/api/branches/{$branch}", ['is_active' => false])->assertNotFound();
});

function registerTendero($test, string $email, string $name): string
{
    $presetId = BusinessTypePreset::query()->where('name', 'corner_store')->value('id');

    return $test->postJson('/api/register', [
        'full_name' => $name,
        'email' => $email,
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'business_name' => $name.' Store',
        'business_type_preset_id' => $presetId,
    ])->assertCreated()->json('token');
}

function createShelfProduct($test, string $token, array $overrides = []): int
{
    $unitId = UnitOfMeasure::query()->where('abbreviation', 'und')->value('id');
    $categoryId = $test->withToken($token)->getJson('/api/categories')->json('0.id');

    return $test->withToken($token)->postJson('/api/products', array_merge([
        'name' => 'Rice',
        'category_id' => $categoryId,
        'unit_of_measure_id' => $unitId,
        'cost_price' => 2000,
        'sale_price' => 3000,
        'current_stock' => 10,
        'minimum_stock' => 0,
    ], $overrides))->assertCreated()->json('id');
}

function creditCustomer($test, string $token, string $name): int
{
    return $test->withToken($token)->postJson('/api/credit-customers', [
        'full_name' => $name,
        'credit_limit' => 50000,
    ])->assertCreated()->json('id');
}

function creditSale($test, string $token, int $customerId, int $productId, string $dueOn): void
{
    $test->withToken($token)->postJson('/api/sales', [
        'payment_method' => 'credit',
        'credit_customer_id' => $customerId,
        'due_on' => $dueOn,
        'lines' => [
            ['product_id' => $productId, 'quantity' => 1],
        ],
    ])->assertCreated();
}

function deliveryOrder($test, string $token, int $customerId, int $productId, float $quantity): int
{
    return $test->withToken($token)->postJson('/api/delivery-orders', [
        'end_customer_id' => $customerId,
        'delivery_address' => 'Calle 10',
        'payment_method' => 'cash',
        'lines' => [
            ['product_id' => $productId, 'quantity' => $quantity],
        ],
    ])->assertCreated()->json('id');
}

function stockOf(int $productId): float
{
    return (float) Product::query()->withoutGlobalScopes()->findOrFail($productId)->current_stock;
}
