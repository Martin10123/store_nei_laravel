<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryMovementController extends Controller
{
    public function index()
    {
        return InventoryMovement::query()
            ->with('product:id,name')
            ->latest()
            ->limit(50)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'movement_type' => ['required', Rule::in(['in', 'out', 'waste', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($data['movement_type'] !== 'adjustment' && (float) $data['quantity'] <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad tiene que ser mayor que cero.',
            ]);
        }

        $movement = DB::transaction(function () use ($request, $data) {
            $product = Product::query()->where('is_active', true)->lockForUpdate()->find($data['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Ese producto no está en tu inventario.',
                ]);
            }

            $current = round((float) $product->current_stock, 3);
            $amount = round((float) $data['quantity'], 3);
            $signed = match ($data['movement_type']) {
                'in' => $amount,
                'out', 'waste' => -$amount,
                'adjustment' => round($amount - $current, 3),
            };
            $next = round($current + $signed, 3);

            if ($signed == 0.0) {
                throw ValidationException::withMessages([
                    'quantity' => 'El stock ya está en esa cantidad.',
                ]);
            }

            if ($next < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "No hay suficiente stock de {$product->name}.",
                ]);
            }

            $product->update(['current_stock' => $next]);

            return InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'movement_type' => $data['movement_type'],
                'quantity' => $signed,
                'reason' => $data['reason'] ?? null,
            ]);
        });

        $movement->load('product:id,name');

        return response()->json($movement, 201);
    }
}
