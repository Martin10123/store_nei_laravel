<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'abbreviation'])]
class UnitOfMeasure extends Model
{
    protected $table = 'units_of_measure';

    public $timestamps = false;
}
