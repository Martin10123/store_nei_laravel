<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder) {
            $businessId = request()->user()?->business_id;

            if ($businessId) {
                $builder->where($builder->getModel()->getTable().'.business_id', $businessId);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->getAttribute('business_id') && request()->user()?->business_id) {
                $model->setAttribute('business_id', request()->user()->business_id);
            }
        });
    }
}
