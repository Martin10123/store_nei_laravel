<?php

namespace App\Http\Controllers;

use App\Models\PriceQuote;
use App\Models\PriceSource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PriceQuoteController extends Controller
{
    public function index()
    {
        return PriceQuote::query()
            ->with(['product:id,name', 'priceSource:id,name'])
            ->latest()
            ->limit(100)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'found_name' => ['required', 'string', 'max:200'],
            'found_price' => ['required', 'numeric', 'min:0'],
            'source_name' => ['nullable', 'string', 'max:120'],
        ]);

        $source = null;

        if (! empty($data['source_name'])) {
            $source = PriceSource::query()->where('name', $data['source_name'])->first();

            if (! $source) {
                $source = PriceSource::create([
                    'name' => $data['source_name'],
                    'is_active' => true,
                ]);
            }
        }

        $quote = PriceQuote::create([
            'price_source_id' => $source?->id,
            'found_name' => $data['found_name'],
            'found_price' => round((float) $data['found_price'], 2),
        ]);
        $quote->load(['product:id,name', 'priceSource:id,name']);

        return response()->json($quote, 201);
    }

    public function assign(Request $request, PriceQuote $priceQuote)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::query()->where('is_active', true)->find($data['product_id']);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'Ese producto no está en tu inventario.',
            ]);
        }

        $priceQuote->update(['product_id' => $product->id]);
        $priceQuote->load(['product:id,name', 'priceSource:id,name']);

        return $priceQuote;
    }
}
