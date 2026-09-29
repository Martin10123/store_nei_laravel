<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id',
    'end_customer_id',
    'status',
    'delivery_address',
    'total',
    'payment_method',
    'notes',
    'origin',
    'sale_id',
])]
class DeliveryOrder extends Model
{
    use BelongsToBusiness;

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function endCustomer(): BelongsTo
    {
        return $this->belongsTo(EndCustomer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
