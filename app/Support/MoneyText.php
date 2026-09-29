<?php

namespace App\Support;

class MoneyText
{
    public static function pesos(float $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }
}
