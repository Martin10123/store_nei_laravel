<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_id', 'phone_number', 'direction', 'body', 'detected_intent'])]
class WhatsappMessage extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;
}
