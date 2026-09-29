<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index()
    {
        return Sale::query()
            ->with(['lines.product:id,name', 'creditCustomer:id,full_name'])
            ->latest()
            ->limit(50)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'nequi', 'daviplata', 'credit', 'card'])],
            'credit_customer_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['payment_method'] === 'credit' && empty($data['credit_customer_id'])) {
            throw ValidationException::withMessages([
                'credit_customer_id' => 'Elige a quién se le fía.',
            ]);
        }

        if ($data['payment_method'] !== 'credit' && ! empty($data['credit_customer_id'])) {
            throw ValidationException::withMessages([
                'credit_customer_id' => 'El cliente fiado solo aplica cuando el pago es fiado.',
            ]);
        }

        $sale = DB::transaction(function () use ($request, $data) {
            $customer = null;

            if (! empty($data['credit_customer_id'])) {
                $customer = CreditCustomer::query()->lockForUpdate()->find($data['credit_customer_id']);

                if (! $customer || ! $customer->is_active) {
                    throw ValidationException::withMessages([
                        'credit_customer_id' => 'Ese fiado no está activo.',
                    ]);
                }
            }

            $prepared = [];
            $total = 0.0;

            foreach ($data['lines'] as $line) {
                $product = Product::query()->where('is_active', true)->lockForUpdate()->find($line['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'lines' => 'Hay un producto que no está en tu inventario.',
                    ]);
                }

                $quantity = round((float) $line['quantity'], 3);
                $stock = round((float) $product->current_stock, 3);

                if ($quantity > $stock) {
                    throw ValidationException::withMessages([
                        'lines' => "No hay suficiente stock de {$product->name}.",
                    ]);
                }

                $unitPrice = round((float) ($line['unit_price'] ?? $product->sale_price), 2);
                $subtotal = round($quantity * $unitPrice, 2);
                $product->update(['current_stock' => round($stock - $quantity, 3)]);

                $prepared[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => round((float) $product->cost_price, 2),
                    'subtotal' => $subtotal,
                ];
                $total = round($total + $subtotal, 2);
            }

            if ($customer && $customer->credit_limit !== null) {
                $nextBalance = round((float) $customer->current_balance + $total, 2);

                if ($nextBalance > round((float) $customer->credit_limit, 2)) {
                    throw ValidationException::withMessages([
                        'credit_customer_id' => 'Ese fiado supera el límite de crédito.',
                    ]);
                }
            }

            $sale = Sale::create([
                'user_id' => $request->user()->id,
                'credit_customer_id' => $customer?->id,
                'total' => $total,
                'payment_method' => $data['payment_method'],
            ]);

            foreach ($prepared as $line) {
                $sale->lines()->create($line);
            }

            if ($customer) {
                $customer->update([
                    'current_balance' => round((float) $customer->current_balance + $total, 2),
                ]);
                $customer->movements()->create([
                    'sale_id' => $sale->id,
                    'movement_type' => 'charge',
                    'amount' => $total,
                ]);
            }

            return $sale;
        });

        $sale->load(['lines.product:id,name', 'creditCustomer:id,full_name']);

        return response()->json($sale, 201);
    }
}
