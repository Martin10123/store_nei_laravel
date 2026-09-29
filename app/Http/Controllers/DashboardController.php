<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\DailyClose;
use App\Models\Product;
use App\Models\Sale;
use App\Support\BogotaDay;

class DashboardController extends Controller
{
    public function index()
    {
        [$today, $start, $end] = BogotaDay::bounds();

        $todaySales = round((float) Sale::query()->whereBetween('created_at', [$start, $end])->sum('total'), 2);
        $lowStock = Product::query()
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->count();

        return [
            'today' => $today,
            'today_sales_total' => number_format($todaySales, 2, '.', ''),
            'low_stock_count' => $lowStock,
            'open_alerts' => Alert::query()
                ->where('is_resolved', false)
                ->with(['product:id,name', 'creditCustomer:id,full_name'])
                ->latest()
                ->limit(20)
                ->get(),
            'recent_closes' => DailyClose::query()
                ->with('topProduct:id,name')
                ->orderByDesc('closed_on')
                ->limit(7)
                ->get(),
        ];
    }
}
