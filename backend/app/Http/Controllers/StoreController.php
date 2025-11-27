<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    // Create a new store for authenticated user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'country' => 'nullable|string|size:2',
            'currency' => 'nullable|string|size:3',
        ]);

        $user = $request->user();

        $slug = Str::slug($request->input('name'));
        // ensure unique
        $base = $slug;
        $i = 1;
        while (Store::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $store = Store::create([
            'owner_id' => $user->id,
            'name' => $request->input('name'),
            'slug' => $slug,
            'description' => $request->input('description'),
            'country' => $request->input('country', null),
            'currency' => $request->input('currency', 'USD'),
        ]);

        return response()->json(['store' => $store], 201);
    }

    // Get store info by slug
    public function show($slug)
    {
        $store = Store::where('slug', $slug)->firstOrFail();
        return response()->json(['store' => $store]);
    }

    // Update store (owner only)
    public function update(Request $request, $id)
    {
        $store = Store::findOrFail($id);
        $this->authorize('update', $store);

        $store->fill($request->only(['name', 'description', 'country', 'currency', 'settings']));
        $store->save();

        return response()->json(['store' => $store]);
    }

    // Owner-initiated cashout (Stripe Connect payout)
    public function cashout(Request $request, $id)
    {
        $store = Store::findOrFail($id);
        $this->authorize('update', $store);

        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $amount = (float)$request->input('amount');

        try {
            $stripeService = new \App\Services\StripeConnectService();
            $payout = $stripeService->initiateTransfer($store, $amount, $store->currency ?? 'USD');
            return response()->json(['payout' => $payout], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // Get cashout history
    public function payoutHistory(Request $request, $id)
    {
        $store = Store::findOrFail($id);
        $this->authorize('update', $store);

        $payouts = \App\Models\Payout::where('store_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json(['payouts' => $payouts]);
    }
}
