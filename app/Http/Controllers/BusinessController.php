<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function update(Request $request)
    {
        /** @var Business $business */
        $business = $request->user()->business;

        if ($request->exists('public_slug')) {
            $slug = $request->input('public_slug');
            $request->merge([
                'public_slug' => $slug === null || $slug === '' ? null : strtolower((string) $slug),
            ]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'public_slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('businesses', 'public_slug')->ignore($business->id),
            ],
        ], [
            'public_slug.unique' => 'Ese enlace público ya está en uso.',
            'public_slug.regex' => 'Usa solo letras minúsculas, números y guiones.',
        ]);

        $business->update($data);
        $business->load('preset');

        return response()->json($business);
    }
}
