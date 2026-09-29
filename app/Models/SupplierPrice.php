<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'product_id', 'supplier_id', 'price', 'source', 'recorded_at'])]
class SupplierPrice extends Model
{
    use BelongsToBusiness;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
