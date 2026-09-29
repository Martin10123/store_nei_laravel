<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'alert_type', 'product_id', 'credit_customer_id', 'message', 'is_resolved'])]
class Alert extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creditCustomer(): BelongsTo
    {
        return $this->belongsTo(CreditCustomer::class);
    }
}
