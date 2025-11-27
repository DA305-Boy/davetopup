<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentLink;
use Illuminate\Support\Str;

class PaymentLinkController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // get links for user's stores
        $links = PaymentLink::whereHas('store', fn($q) => $q->where('owner_id', $user->id))->get();
        return response()->json(['payment_links' => $links]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'items' => 'nullable|array',
        ]);

        $user = $request->user();
        $store = $user->stores()->findOrFail($request->input('store_id'));

        $link = PaymentLink::create([
            'store_id' => $store->id,
            'token' => Str::random(32),
            'title' => $request->input('title', 'Payment'),
            'description' => $request->input('description'),
            'amount' => $request->input('amount'),
            'currency' => $request->input('currency', 'USD'),
            'items' => $request->input('items'),
        ]);

        return response()->json(['payment_link' => $link], 201);
    }

    // Public: fetch link by token (no auth required)
    public function publicShow($token)
    {
        $link = PaymentLink::where('token', $token)->where('status', 'active')->firstOrFail();
        return response()->json(['payment_link' => $link]);
    }
}
