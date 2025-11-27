<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class AdminController extends Controller
{
    public function orders(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $q = Order::query();
        if ($request->query('status')) {
            $q->where('status', $request->query('status'));
        }
        return response()->json(['orders' => $q->orderBy('created_at','desc')->paginate(25)]);
    }

    public function refund(Request $request, $id)
    {
        $this->authorize('refund', Order::class);
        $order = Order::findOrFail($id);
        // stub: call PaymentService->refund($order)
        $order->status = 'refunded';
        $order->save();
        return response()->json(['order' => $order]);
    }

    public function markDelivered(Request $request, $id)
    {
        $this->authorize('update', Order::class);
        $order = Order::findOrFail($id);
        $order->status = 'delivered';
        $order->save();
        return response()->json(['order' => $order]);
    }
}
