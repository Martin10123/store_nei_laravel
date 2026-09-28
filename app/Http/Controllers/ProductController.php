<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function units()
    {
        return UnitOfMeasure::query()->orderBy('name')->get();
    }

    public function index()
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'unitOfMeasure:id,name,abbreviation'])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $product = Product::create($this->attributes($request));
        $product->load(['category:id,name', 'unitOfMeasure:id,name,abbreviation']);

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->attributes($request, $product));
        $product->load(['category:id,name', 'unitOfMeasure:id,name,abbreviation']);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return response()->noContent();
    }

    private function attributes(Request $request, ?Product $product = null): array
    {
        $request->merge([
            'barcode' => $request->filled('barcode') ? $request->input('barcode') : null,
        ]);

        $businessId = $request->user()->business_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'barcode')
                    ->where('business_id', $businessId)
                    ->ignore($product?->id),
            ],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
        ]);

        if (! empty($data['category_id'])) {
            $allowed = ProductCategory::query()
                ->whereKey($data['category_id'])
                ->where(function ($query) use ($request) {
                    $business = $request->user()->business;
                    $query->where('business_id', $business->id)
                        ->orWhere(function ($query) use ($business) {
                            $query->whereNull('business_id')
                                ->where('business_type_preset_id', $business->business_type_preset_id);
                        });
                })
                ->exists();

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'category_id' => 'Esa categoría no pertenece a tu tienda.',
                ]);
            }
        }

        return $data;
    }
}
