<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        return ProductCategory::query()
            ->where(function ($query) use ($business) {
                $query->where('business_id', $business->id)
                    ->orWhere(function ($query) use ($business) {
                        $query->whereNull('business_id')
                            ->where('business_type_preset_id', $business->business_type_preset_id);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $business = $request->user()->business;

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')->where('business_id', $business->id),
            ],
        ]);

        $category = ProductCategory::create([
            'business_id' => $business->id,
            'business_type_preset_id' => null,
            'name' => $data['name'],
        ]);

        return response()->json($category, 201);
    }
}
