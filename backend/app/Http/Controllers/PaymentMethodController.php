<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $methods = PaymentMethod::where('user_id', $user->id)->get();
        return response()->json(['payment_methods' => $methods]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:card,bank,paypal,binance,crypto',
            'provider' => 'nullable|string',
            'external_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();

        $method = PaymentMethod::create([
            'user_id' => $user->id,
            'type' => $request->input('type'),
            'provider' => $request->input('provider'),
            'external_id' => $request->input('external_id'),
            'metadata' => $request->input('metadata'),
            'verified' => false,
        ]);

        return response()->json(['payment_method' => $method], 201);
    }

    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        $method = PaymentMethod::findOrFail($id);

        if ($method->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return response()->json(['payment_method' => $method]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $method = PaymentMethod::findOrFail($id);

        if ($method->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $method->delete();
        return response()->json([], 204);
    }
}
