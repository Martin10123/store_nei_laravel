<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\WhatsappMessage;
use App\Support\BogotaDay;
use App\Support\MoneyText;
use App\Support\StockAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsappMessageController extends Controller
{
    public function index()
    {
        return WhatsappMessage::query()
            ->latest()
            ->limit(40)
            ->get()
            ->reverse()
            ->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $parsed = $this->detect($data['body']);
        $reply = $this->reply($request, $parsed);

        $inbound = WhatsappMessage::create([
            'phone_number' => $data['phone_number'],
            'direction' => 'inbound',
            'body' => $data['body'],
            'detected_intent' => $parsed['intent'],
        ]);

        $outbound = WhatsappMessage::create([
            'phone_number' => $data['phone_number'],
            'direction' => 'outbound',
            'body' => $reply,
            'detected_intent' => $parsed['intent'],
        ]);

        return response()->json([
            'inbound' => $inbound,
            'outbound' => $outbound,
            'reply' => $reply,
        ], 201);
    }

    private function detect(string $body): array
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $body)));

        if (preg_match('/^(?:vender|vende|registra venta|registrar venta)\s+(\d+(?:[.,]\d{1,3})?)\s+(.+)$/u', $text, $matches)) {
            return [
                'intent' => 'record_sale',
                'quantity' => (float) str_replace(',', '.', $matches[1]),
                'product_name' => trim($matches[2]),
            ];
        }

        if (preg_match('/^(?:saldo|fiado)(?:\s+de)?\s+(.+)$/u', $text, $matches)) {
            return [
                'intent' => 'credit_balance',
                'customer_name' => trim($matches[1]),
            ];
        }

        if (preg_match('/^(?:ventas(?: de hoy)?|cu[aá]nto vend[ií](?: hoy)?|total de ventas(?: de hoy)?)$/u', $text)) {
            return ['intent' => 'today_sales'];
        }

        return ['intent' => 'unknown'];
    }

    private function reply(Request $request, array $parsed): string
    {
        return match ($parsed['intent']) {
            'today_sales' => $this->todaySales(),
            'credit_balance' => $this->creditBalance($parsed['customer_name']),
            'record_sale' => $this->recordSale($request, (float) $parsed['quantity'], $parsed['product_name']),
            default => 'Puedo decirte las ventas de hoy, el saldo de un fiado o registrar una venta. Prueba con: ventas de hoy, saldo de Pedro, vender 1 arroz.',
        };
    }

    private function todaySales(): string
    {
        [, $start, $end] = BogotaDay::bounds();
        $total = round((float) Sale::query()->whereBetween('created_at', [$start, $end])->sum('total'), 2);

        return 'Hoy se han vendido '.MoneyText::pesos($total).'.';
    }

    private function creditBalance(string $name): string
    {
        $matches = CreditCustomer::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (CreditCustomer $customer) => mb_strtolower($customer->full_name) === mb_strtolower($name))
            ->values();

        if ($matches->isEmpty()) {
            return "No encontré un fiado llamado {$name}.";
        }

        if ($matches->count() > 1) {
            return 'Hay más de un fiado con ese nombre.';
        }

        $customer = $matches->first();

        return 'El saldo de '.$customer->full_name.' es '.MoneyText::pesos((float) $customer->current_balance).'.';
    }

    private function recordSale(Request $request, float $quantity, string $productName): string
    {
        $quantity = round($quantity, 3);

        if ($quantity <= 0) {
            return 'La cantidad tiene que ser mayor que cero.';
        }

        $matches = Product::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Product $product) => mb_strtolower($product->name) === mb_strtolower($productName))
            ->values();

        if ($matches->isEmpty()) {
            return 'No encontré ese producto en el inventario.';
        }

        if ($matches->count() > 1) {
            return 'Hay más de un producto con ese nombre.';
        }

        try {
            $sold = DB::transaction(function () use ($request, $matches, $quantity) {
                $product = Product::query()->where('is_active', true)->lockForUpdate()->find($matches->first()->id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'body' => 'No encontré ese producto en el inventario.',
                    ]);
                }

                $available = StockAvailability::available($product);

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'body' => "No hay suficiente stock de {$product->name}.",
                    ]);
                }

                $stock = round((float) $product->current_stock, 3);
                $unitPrice = round((float) $product->sale_price, 2);
                $subtotal = round($quantity * $unitPrice, 2);
                $product->update(['current_stock' => round($stock - $quantity, 3)]);

                $sale = Sale::create([
                    'user_id' => $request->user()->id,
                    'total' => $subtotal,
                    'payment_method' => 'cash',
                ]);

                $sale->lines()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => round((float) $product->cost_price, 2),
                    'subtotal' => $subtotal,
                ]);

                return [$product->name, $subtotal, round($stock - $quantity, 3)];
            });
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first() ?? 'No pude registrar la venta.';
        }

        return "Listo. Registré una venta de {$quantity} {$sold[0]} por ".MoneyText::pesos($sold[1]).'. Quedan '.$this->quantity($sold[2]).'.';
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
