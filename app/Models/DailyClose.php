<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'closed_on', 'total_sold', 'total_cost', 'total_credit', 'top_product_id', 'ai_summary'])]
class DailyClose extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'closed_on' => 'date:Y-m-d',
            'total_sold' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    public function topProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'top_product_id');
    }
}
