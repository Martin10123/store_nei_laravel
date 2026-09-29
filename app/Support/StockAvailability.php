<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockReservation;

class StockAvailability
{
    public static function reservedQuantity(int $productId, ?int $exceptDeliveryOrderId = null): float
    {
        $query = StockReservation::query()
            ->where('product_id', $productId)
            ->where('is_released', false);

        if ($exceptDeliveryOrderId) {
            $query->where('delivery_order_id', '!=', $exceptDeliveryOrderId);
        }

        return round((float) $query->sum('reserved_quantity'), 3);
    }

    public static function available(Product $product, ?int $exceptDeliveryOrderId = null): float
    {
        return round((float) $product->current_stock - self::reservedQuantity($product->id, $exceptDeliveryOrderId), 3);
    }
}
