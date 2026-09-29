<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'name', 'phone', 'address'])]
class EndCustomer extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }
}
