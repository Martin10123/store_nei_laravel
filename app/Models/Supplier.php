<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name', 'contact_name', 'phone', 'address', 'is_active'])]
class Supplier extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SupplierPrice::class);
    }
}
