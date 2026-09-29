<?php

namespace App\Http\Controllers;

use App\Models\DailyClose;
use App\Models\Sale;
use App\Models\SaleLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DailyCloseController extends Controller
{
    public function index()
    {
        return DailyClose::query()
            ->with('topProduct:id,name')
            ->orderByDesc('closed_on')
            ->limit(30)
            ->get();
    }

    public function preview(Request $request)
    {
        return $this->snapshot($request, false);
    }

    public function store(Request $request)
    {
        return $this->snapshot($request, true);
    }

    private function snapshot(Request $request, bool $persist)
    {
        $closedOn = $request->validate([
            'closed_on' => ['nullable', 'date'],
        ])['closed_on'] ?? Carbon::now('America/Bogota')->toDateString();

        $existing = DailyClose::query()->whereDate('closed_on', $closedOn)->first();

        if ($persist && $existing) {
            throw ValidationException::withMessages([
                'closed_on' => 'Ese día ya está cerrado.',
            ]);
        }

        $start = Carbon::parse($closedOn, 'America/Bogota')->startOfDay()->utc();
        $end = Carbon::parse($closedOn, 'America/Bogota')->endOfDay()->utc();

        $sales = Sale::query()->whereBetween('created_at', [$start, $end]);
        $totalSold = round((float) (clone $sales)->sum('total'), 2);
        $totalCredit = round((float) (clone $sales)->where('payment_method', 'credit')->sum('total'), 2);

        $saleIds = (clone $sales)->pluck('id');
        $totalCost = round((float) SaleLine::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('coalesce(sum(quantity * unit_cost), 0) as total')
            ->value('total'), 2);

        $topProductId = SaleLine::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('product_id, sum(quantity) as sold')
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->value('product_id');

        if (! $persist) {
            return [
                'closed_on' => $closedOn,
                'total_sold' => number_format($totalSold, 2, '.', ''),
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'total_credit' => number_format($totalCredit, 2, '.', ''),
                'top_product_id' => $topProductId,
                'already_closed' => $existing !== null,
            ];
        }

        $close = DailyClose::create([
            'closed_on' => $closedOn,
            'total_sold' => $totalSold,
            'total_cost' => $totalCost,
            'total_credit' => $totalCredit,
            'top_product_id' => $topProductId,
        ]);
        $close->load('topProduct:id,name');

        return response()->json($close, 201);
    }
}
