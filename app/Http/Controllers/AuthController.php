<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessTypePreset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function presets()
    {
        return BusinessTypePreset::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'config']);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:150'],
            'business_type_preset_id' => ['required', 'integer', 'exists:business_type_presets,id'],
        ], [
            'email.unique' => 'Ese correo ya tiene una tienda.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $business = Business::create([
                'name' => $data['business_name'],
                'business_type_preset_id' => $data['business_type_preset_id'],
            ]);

            return User::create([
                'business_id' => $business->id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'owner',
            ]);
        });

        return response()->json($this->session($user), 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        return response()->json($this->session($user));
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('business.preset');

        return response()->json([
            'user' => $this->userPayload($user),
            'business' => $user->business,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function session(User $user): array
    {
        $user->load('business.preset');

        return [
            'token' => $user->createToken('panel')->plainTextToken,
            'user' => $this->userPayload($user),
            'business' => $user->business,
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
