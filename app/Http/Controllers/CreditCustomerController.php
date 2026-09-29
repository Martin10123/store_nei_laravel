<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditCustomerController extends Controller
{
    public function index()
    {
        return CreditCustomer::query()->orderBy('full_name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = CreditCustomer::create($data);

        return response()->json($customer, 201);
    }

    public function movements(CreditCustomer $creditCustomer)
    {
        return $creditCustomer->movements()->latest()->limit(50)->get();
    }

    public function pay(Request $request, CreditCustomer $creditCustomer)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $result = DB::transaction(function () use ($creditCustomer, $data) {
            $customer = CreditCustomer::query()->lockForUpdate()->find($creditCustomer->id);
            $amount = round((float) $data['amount'], 2);
            $balance = round((float) $customer->current_balance, 2);

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'El abono no puede ser mayor que el saldo.',
                ]);
            }

            $customer->update(['current_balance' => round($balance - $amount, 2)]);

            $movement = $customer->movements()->create([
                'movement_type' => 'payment',
                'amount' => $amount,
            ]);

            return [$customer->fresh(), $movement];
        });

        return response()->json([
            'customer' => $result[0],
            'movement' => $result[1],
        ], 201);
    }
}
