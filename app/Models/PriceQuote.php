<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'price_source_id', 'product_id', 'found_name', 'found_price'])]
class PriceQuote extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'found_price' => 'decimal:2',
        ];
    }

    public function priceSource(): BelongsTo
    {
        return $this->belongsTo(PriceSource::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
