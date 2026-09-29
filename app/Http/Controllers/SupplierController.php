<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index()
    {
        return Supplier::query()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
        ]);

        $supplier = Supplier::create($data);

        return response()->json($supplier, 201);
    }

    public function prices()
    {
        return SupplierPrice::query()
            ->with(['product:id,name,cost_price', 'supplier:id,name'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function storePrice(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'source' => ['nullable', Rule::in(['manual'])],
        ]);

        $product = Product::query()->where('is_active', true)->find($data['product_id']);
        $supplier = Supplier::query()->where('is_active', true)->find($data['supplier_id']);

        if (! $product || ! $supplier) {
            throw ValidationException::withMessages([
                'product_id' => 'El producto y el proveedor tienen que ser de tu tienda.',
            ]);
        }

        $price = SupplierPrice::create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => round((float) $data['price'], 2),
            'source' => 'manual',
        ]);
        $price->load(['product:id,name,cost_price', 'supplier:id,name']);

        return response()->json($price, 201);
    }
}
