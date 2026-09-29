<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\CreditCustomer;
use App\Models\Product;
use App\Support\BogotaDay;
use App\Support\MoneyText;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AlertController extends Controller
{
    public function index()
    {
        return Alert::query()
            ->with(['product:id,name', 'creditCustomer:id,full_name'])
            ->latest()
            ->limit(100)
            ->get();
    }

    public function refresh()
    {
        $today = BogotaDay::bounds()[0];
        $expirationLimit = Carbon::parse($today)->addDays(7)->toDateString();

        Product::query()
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('id')
            ->get()
            ->each(function (Product $product) {
                $this->open('low_stock', $product->id, null, sprintf(
                    'Quedan %s de %s. El mínimo es %s.',
                    $this->quantity($product->current_stock),
                    $product->name,
                    $this->quantity($product->minimum_stock),
                ));
            });

        Product::query()
            ->where('is_active', true)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $expirationLimit)
            ->orderBy('id')
            ->get()
            ->each(function (Product $product) {
                $date = Carbon::parse($product->expiration_date)->toDateString();
                $this->open('expiration', $product->id, null, "{$product->name} vence el {$date}.");
            });

        CreditCustomer::query()
            ->where('current_balance', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(function (CreditCustomer $customer) use ($today) {
                $overdue = $customer->movements()
                    ->where('movement_type', 'charge')
                    ->whereNotNull('due_on')
                    ->whereDate('due_on', '<', $today)
                    ->exists();

                if (! $overdue) {
                    return;
                }

                $this->open(
                    'overdue_credit',
                    null,
                    $customer->id,
                    "{$customer->full_name} tiene un fiado vencido y todavía debe ".MoneyText::pesos((float) $customer->current_balance).'.',
                );
            });

        return $this->index()->where('is_resolved', false)->values();
    }

    public function update(Alert $alert)
    {
        $alert->update(['is_resolved' => true]);
        $alert->load(['product:id,name', 'creditCustomer:id,full_name']);

        return $alert;
    }

    private function open(string $type, ?int $productId, ?int $customerId, string $message): void
    {
        $exists = Alert::query()
            ->where('alert_type', $type)
            ->where('is_resolved', false)
            ->when(
                $productId,
                fn ($query) => $query->where('product_id', $productId),
                fn ($query) => $query->whereNull('product_id'),
            )
            ->when(
                $customerId,
                fn ($query) => $query->where('credit_customer_id', $customerId),
                fn ($query) => $query->whereNull('credit_customer_id'),
            )
            ->exists();

        if ($exists) {
            return;
        }

        Alert::create([
            'alert_type' => $type,
            'product_id' => $productId,
            'credit_customer_id' => $customerId,
            'message' => Str::limit($message, 250, ''),
        ]);
    }

    private function quantity(mixed $value): string
    {
        $number = round((float) $value, 3);

        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }
}
