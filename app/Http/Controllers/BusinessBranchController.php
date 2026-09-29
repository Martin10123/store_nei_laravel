<?php

namespace App\Http\Controllers;

use App\Models\BusinessBranch;
use Illuminate\Http\Request;

class BusinessBranchController extends Controller
{
    public function index()
    {
        return BusinessBranch::query()->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $branch = BusinessBranch::create($data);

        return response()->json($branch, 201);
    }

    public function update(Request $request, BusinessBranch $businessBranch)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $businessBranch->update($data);

        return $businessBranch;
    }
}
