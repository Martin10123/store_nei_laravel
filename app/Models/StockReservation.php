<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_order_id', 'product_id', 'reserved_quantity', 'is_released'])]
class StockReservation extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'reserved_quantity' => 'decimal:3',
            'is_released' => 'boolean',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
