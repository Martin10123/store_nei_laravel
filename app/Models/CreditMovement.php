<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['credit_customer_id', 'sale_id', 'movement_type', 'amount'])]
class CreditMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function creditCustomer(): BelongsTo
    {
        return $this->belongsTo(CreditCustomer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
