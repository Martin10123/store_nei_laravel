<?php

namespace App\Http\Controllers;

use App\Models\EndCustomer;
use Illuminate\Http\Request;

class EndCustomerController extends Controller
{
    public function index()
    {
        return EndCustomer::query()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
        ]);

        $customer = EndCustomer::create($data);

        return response()->json($customer, 201);
    }
}
