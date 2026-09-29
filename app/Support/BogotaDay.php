<?php

namespace App\Support;

use Carbon\Carbon;

class BogotaDay
{
    /** @return array{0: string, 1: Carbon, 2: Carbon} */
    public static function bounds(?string $day = null): array
    {
        $date = $day ?? Carbon::now('America/Bogota')->toDateString();
        $start = Carbon::parse($date, 'America/Bogota')->startOfDay()->utc();
        $end = Carbon::parse($date, 'America/Bogota')->endOfDay()->utc();

        return [$date, $start, $end];
    }
}
