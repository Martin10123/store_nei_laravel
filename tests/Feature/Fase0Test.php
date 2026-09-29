<?php

use App\Models\BusinessTypePreset;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registers a store, creates a product, and hides it from another store', function () {
    expect(config('database.default'))->toBe('sqlite');

    $this->seed();

    $preset = BusinessTypePreset::query()->where('name', 'fruit_shop')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('abbreviation', 'kg')->firstOrFail();

    $registration = $this->postJson('/api/register', [
        'full_name' => 'Ana',
        'email' => 'ana@store.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'business_name' => 'Ana Fruit',
        'business_type_preset_id' => $preset->id,
    ]);

    $registration->assertCreated();
    $token = $registration->json('token');

    $categoryId = $this->withToken($token)
        ->getJson('/api/categories')
        ->assertOk()
        ->json('0.id');

    $this->withToken($token)->postJson('/api/products', [
        'name' => 'Tomato',
        'category_id' => $categoryId,
        'unit_of_measure_id' => $unit->id,
        'sale_price' => 2500,
        'current_stock' => 12.5,
    ])->assertCreated();

    $this->app['auth']->forgetGuards();

    $other = $this->postJson('/api/register', [
        'full_name' => 'Luis',
        'email' => 'luis@store.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'business_name' => 'Luis Store',
        'business_type_preset_id' => BusinessTypePreset::query()->where('name', 'corner_store')->value('id'),
    ])->assertCreated();

    $this->withToken($other->json('token'))
        ->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(0);

    expect(Product::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('private api answers 401 when there is no token', function () {
    $this->getJson('/api/products')->assertUnauthorized();
    $this->getJson('/api/dashboard')->assertUnauthorized();
});
