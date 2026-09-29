<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name', 'is_active'])]
class PriceSource extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(PriceQuote::class);
    }
}
