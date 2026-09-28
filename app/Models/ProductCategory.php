<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'business_type_preset_id', 'name'])]
class ProductCategory extends Model
{
    protected $table = 'product_categories';

    public $timestamps = false;

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(BusinessTypePreset::class, 'business_type_preset_id');
    }
}
