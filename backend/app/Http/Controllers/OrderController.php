<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|string',
            'items.*.name' => 'required|string|max:255',
            'items.*.game' => 'required|string|max:100',
            'items.*.price' => 'required|numeric|min:0.01',
            'items.*.quantity' => 'required|integer|min:1',
            'email' => 'required|email|max:255',
            'playerUid' => 'required|string|min:3|max:50',
            'playerNickname' => 'required|string|min:2|max:50',
            'phone' => 'nullable|string|max:20',
        ]);

        try {
            // Calculate totals
            $subtotal = collect($validated['items'])->sum(fn($item) => $item['price'] * $item['quantity']);
            $tax = round($subtotal * 0.08, 2); // 8% tax
            $total = $subtotal + $tax;

            // Create order
            $order = Order::create([
                'order_id' => 'ORD-' . Str::random(12) . '-' . time(),
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'player_uid' => $validated['playerUid'],
                'player_nickname' => $validated['playerNickname'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'idempotency_key' => Str::uuid(),
            ]);

            // Create order items
            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'name' => $item['name'],
                    'game' => $item['game'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return response()->json([
                'success' => true,
                'orderId' => $order->order_id,
                'amount' => $order->total,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Order creation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
            ], 500);
        }
    }

    /**
     * Get order details
     */
    public function show($id)
    {
        $order = Order::where('order_id', $id)->with('items', 'transactions')->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'orderId' => $order->order_id,
                'email' => $order->email,
                'playerUid' => $order->player_uid,
                'playerNickname' => $order->player_nickname,
                'items' => $order->items->map(fn($item) => [
                    'name' => $item->name,
                    'game' => $item->game,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                ]),
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'total' => $order->total,
                'status' => $order->status,
                'createdAt' => $order->created_at,
                'transactions' => $order->transactions->map(fn($t) => [
                    'id' => $t->transaction_id,
                    'method' => $t->payment_method,
                    'status' => $t->status,
                    'amount' => $t->amount,
                ]),
            ],
        ]);
    }

    /**
     * Get order status only
     */
    public function getStatus($id)
    {
        $order = Order::where('order_id', $id)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $order->status,
            'orderId' => $order->order_id,
        ]);
    }
}
