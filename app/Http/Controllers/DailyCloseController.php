<?php

namespace App\Http\Controllers;

use App\Models\DailyClose;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Support\BogotaDay;
use App\Support\MoneyText;
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
        $requested = $request->validate([
            'closed_on' => ['nullable', 'date'],
        ])['closed_on'] ?? null;

        [$closedOn, $start, $end] = BogotaDay::bounds($requested);

        $existing = DailyClose::query()->whereDate('closed_on', $closedOn)->first();

        if ($persist && $existing) {
            throw ValidationException::withMessages([
                'closed_on' => 'Ese día ya está cerrado.',
            ]);
        }

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

        $topProductName = $topProductId
            ? Product::query()->whereKey($topProductId)->value('name')
            : null;

        $close = DailyClose::create([
            'closed_on' => $closedOn,
            'total_sold' => $totalSold,
            'total_cost' => $totalCost,
            'total_credit' => $totalCredit,
            'top_product_id' => $topProductId,
            'ai_summary' => $this->summary($closedOn, $totalSold, $totalCost, $totalCredit, $topProductName),
        ]);
        $close->load('topProduct:id,name');

        return response()->json($close, 201);
    }

    private function summary(string $closedOn, float $totalSold, float $totalCost, float $totalCredit, ?string $topProductName): string
    {
        $sold = MoneyText::pesos($totalSold);
        $cost = MoneyText::pesos($totalCost);
        $credit = MoneyText::pesos($totalCredit);
        $top = $topProductName
            ? "El producto más vendido fue {$topProductName}."
            : 'No hubo un producto destacado.';

        return "El {$closedOn} se vendió {$sold}, con un costo de {$cost} y {$credit} en fiado. {$top}";
    }
}
