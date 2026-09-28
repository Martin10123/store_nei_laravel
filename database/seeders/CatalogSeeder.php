<?php

namespace Database\Seeders;

use App\Models\BusinessTypePreset;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Kilogramo', 'abbreviation' => 'kg'],
            ['name' => 'Unidad', 'abbreviation' => 'und'],
            ['name' => 'Litro', 'abbreviation' => 'lt'],
            ['name' => 'Gramo', 'abbreviation' => 'gr'],
            ['name' => 'Libra', 'abbreviation' => 'lb'],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::query()->updateOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                ['name' => $unit['name']],
            );
        }

        $presets = [
            [
                'name' => 'fruit_shop',
                'description' => 'Frutas y verduras, con peso variable y vencimiento.',
                'config' => ['requires_expiration' => true, 'variable_weight' => true],
                'categories' => ['Frutas', 'Verduras', 'Hierbas'],
            ],
            [
                'name' => 'deli',
                'description' => 'Carnes frías y quesos, con peso variable y vencimiento.',
                'config' => ['requires_expiration' => true, 'variable_weight' => true],
                'categories' => ['Carnes frías', 'Quesos', 'Embutidos'],
            ],
            [
                'name' => 'liquor_store',
                'description' => 'Licores y cerveza. El horario de venta se aplica más adelante.',
                'config' => ['requires_expiration' => false, 'variable_weight' => false, 'sale_hours' => ['from' => '10:00', 'to' => '03:00']],
                'categories' => ['Cerveza', 'Licores', 'Snacks'],
            ],
            [
                'name' => 'supermarket',
                'description' => 'Surtido amplio de abarrotes y aseo.',
                'config' => ['requires_expiration' => false, 'variable_weight' => false],
                'categories' => ['Abarrotes', 'Aseo', 'Lácteos', 'Bebidas'],
            ],
            [
                'name' => 'corner_store',
                'description' => 'Tienda de barrio.',
                'config' => ['requires_expiration' => false, 'variable_weight' => false],
                'categories' => ['Abarrotes', 'Bebidas', 'Aseo', 'Dulces'],
            ],
        ];

        foreach ($presets as $preset) {
            $type = BusinessTypePreset::query()->updateOrCreate(
                ['name' => $preset['name']],
                [
                    'description' => $preset['description'],
                    'config' => $preset['config'],
                    'is_active' => true,
                ],
            );

            foreach ($preset['categories'] as $name) {
                ProductCategory::query()->updateOrCreate(
                    [
                        'business_type_preset_id' => $type->id,
                        'name' => $name,
                    ],
                    ['business_id' => null],
                );
            }
        }
    }
}
