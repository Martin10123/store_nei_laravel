<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Product;
use App\Support\StockAvailability;

class PublicCatalogController extends Controller
{
    public function products(string $slug)
    {
        $business = Business::query()
            ->where('public_slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $business) {
            return response()->json(['message' => 'No encontramos esa tienda.'], 404);
        }

        $products = Product::query()
            ->withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sale_price', 'current_stock']);

        return [
            'business' => [
                'name' => $business->name,
                'public_slug' => $business->public_slug,
            ],
            'products' => $products->map(fn (Product $product) => [
                'name' => $product->name,
                'sale_price' => $product->sale_price,
                'in_stock' => StockAvailability::available($product) > 0,
            ])->values(),
        ];
    }
}
