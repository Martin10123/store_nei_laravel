<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessTypePreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $preset = BusinessTypePreset::query()->first() ?? BusinessTypePreset::query()->create([
            'name' => 'corner_store',
            'description' => 'Tienda de barrio.',
            'config' => ['requires_expiration' => false, 'variable_weight' => false],
            'is_active' => true,
        ]);

        $business = Business::query()->create([
            'name' => fake()->company(),
            'business_type_preset_id' => $preset->id,
        ]);

        return [
            'business_id' => $business->id,
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'owner',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
