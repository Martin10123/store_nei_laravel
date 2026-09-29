<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\EndCustomer;
use App\Models\Product;
use App\Models\Sale;
use App\Support\StockAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryOrderController extends Controller
{
    public function index()
    {
        return DeliveryOrder::query()
            ->with(['endCustomer:id,name,phone', 'lines.product:id,name'])
            ->latest()
            ->limit(50)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'end_customer_id' => ['required', 'integer'],
            'delivery_address' => ['required', 'string', 'max:200'],
            'payment_method' => ['required', Rule::in(['cash', 'card'])],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $customer = EndCustomer::query()->find($data['end_customer_id']);

        if (! $customer) {
            throw ValidationException::withMessages([
                'end_customer_id' => 'Ese cliente no está en tu tienda.',
            ]);
        }

        $grouped = [];

        foreach ($data['lines'] as $line) {
            $productId = (int) $line['product_id'];
            $grouped[$productId] = round(($grouped[$productId] ?? 0) + (float) $line['quantity'], 3);
        }

        $order = DB::transaction(function () use ($data, $customer, $grouped) {
            $prepared = [];
            $total = 0.0;

            foreach ($grouped as $productId => $quantity) {
                $product = Product::query()->where('is_active', true)->find($productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'lines' => 'Hay un producto que no está en tu inventario.',
                    ]);
                }

                $unitPrice = round((float) $product->sale_price, 2);
                $subtotal = round($quantity * $unitPrice, 2);
                $prepared[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
                $total = round($total + $subtotal, 2);
            }

            $order = DeliveryOrder::create([
                'end_customer_id' => $customer->id,
                'status' => 'pending',
                'delivery_address' => $data['delivery_address'],
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'origin' => 'panel',
            ]);

            foreach ($prepared as $line) {
                $order->lines()->create($line);
            }

            return $order;
        });

        return response()->json($this->payload($order), 201);
    }

    public function confirm(DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Solo se puede confirmar un pedido pendiente.',
            ]);
        }

        DB::transaction(function () use ($deliveryOrder) {
            $order = DeliveryOrder::query()->lockForUpdate()->find($deliveryOrder->id);

            if (! $order || $order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se puede confirmar un pedido pendiente.',
                ]);
            }

            $lines = $order->lines()->orderBy('product_id')->get();

            foreach ($lines as $line) {
                $product = Product::query()->where('is_active', true)->lockForUpdate()->find($line->product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'lines' => 'Hay un producto que no está en tu inventario.',
                    ]);
                }

                if ((float) $line->quantity > StockAvailability::available($product)) {
                    throw ValidationException::withMessages([
                        'lines' => "No hay suficiente stock de {$product->name}.",
                    ]);
                }
            }

            foreach ($lines as $line) {
                $order->reservations()->create([
                    'product_id' => $line->product_id,
                    'reserved_quantity' => $line->quantity,
                ]);
            }

            $order->update(['status' => 'confirmed']);
        });

        return $this->payload($deliveryOrder->fresh());
    }

    public function deliver(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'status' => 'Confirma el pedido antes de entregarlo.',
            ]);
        }

        DB::transaction(function () use ($request, $deliveryOrder) {
            $order = DeliveryOrder::query()->lockForUpdate()->find($deliveryOrder->id);

            if (! $order || $order->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => 'Confirma el pedido antes de entregarlo.',
                ]);
            }

            $lines = $order->lines()->orderBy('product_id')->get();
            $prepared = [];

            foreach ($lines as $line) {
                $product = Product::query()->where('is_active', true)->lockForUpdate()->find($line->product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'lines' => 'Hay un producto que no está en tu inventario.',
                    ]);
                }

                $quantity = round((float) $line->quantity, 3);
                $stock = round((float) $product->current_stock, 3);
                $available = StockAvailability::available($product, $order->id);

                if ($quantity > $stock || $quantity > $available) {
                    throw ValidationException::withMessages([
                        'lines' => "No hay suficiente stock de {$product->name}.",
                    ]);
                }

                $product->update(['current_stock' => round($stock - $quantity, 3)]);
                $prepared[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => round((float) $line->unit_price, 2),
                    'unit_cost' => round((float) $product->cost_price, 2),
                    'subtotal' => round((float) $line->subtotal, 2),
                ];
            }

            $sale = Sale::create([
                'user_id' => $request->user()->id,
                'total' => $order->total,
                'payment_method' => $order->payment_method,
            ]);

            foreach ($prepared as $line) {
                $sale->lines()->create($line);
            }

            $order->reservations()->where('is_released', false)->update(['is_released' => true]);
            $order->update([
                'status' => 'delivered',
                'sale_id' => $sale->id,
            ]);
        });

        return $this->payload($deliveryOrder->fresh());
    }

    public function cancel(DeliveryOrder $deliveryOrder)
    {
        if (in_array($deliveryOrder->status, ['delivered', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Ese pedido ya no se puede cancelar.',
            ]);
        }

        DB::transaction(function () use ($deliveryOrder) {
            $order = DeliveryOrder::query()->lockForUpdate()->find($deliveryOrder->id);

            if (! $order || in_array($order->status, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ese pedido ya no se puede cancelar.',
                ]);
            }

            $order->reservations()->where('is_released', false)->update(['is_released' => true]);
            $order->update(['status' => 'cancelled']);
        });

        return $this->payload($deliveryOrder->fresh());
    }

    private function payload(DeliveryOrder $order): DeliveryOrder
    {
        return $order->load(['endCustomer:id,name,phone', 'lines.product:id,name']);
    }
}
