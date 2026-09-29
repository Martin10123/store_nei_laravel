<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'user_id', 'credit_customer_id', 'total', 'payment_method'])]
class Sale extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function creditCustomer(): BelongsTo
    {
        return $this->belongsTo(CreditCustomer::class);
    }
}
