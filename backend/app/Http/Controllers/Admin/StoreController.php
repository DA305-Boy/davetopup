<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    // Admin-only: create a seller (user + store) with email + password
    public function create(Request $request)
    {
        $admin = $request->user();
        if (!$admin || !($admin->is_admin ?? false)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
            'store_name' => 'required|string|max:255',
            'country' => 'nullable|string|max:2',
            'currency' => 'nullable|string|max:3',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'is_admin' => false,
            ]);

            $slugBase = Str::slug($data['store_name']);
            $slug = $slugBase . '-' . Str::random(6);

            $store = Store::create([
                'owner_id' => $user->id,
                'name' => $data['store_name'],
                'slug' => $slug,
                'country' => $data['country'] ?? 'US',
                'currency' => $data['currency'] ?? 'USD',
            ]);

            DB::commit();

            // Hide sensitive fields before returning
            unset($user->password);

            return response()->json(['user' => $user, 'store' => $store], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create seller', 'error' => $e->getMessage()], 500);
        }
    }
}
