<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Payout;
use App\Models\SellerVerification;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        return response()->json([
            'total_orders' => Order::count(),
            'total_revenue' => Order::sum('total'),
            'total_payouts' => Payout::where('status', 'completed')->sum('amount'),
            'pending_verifications' => SellerVerification::where('verification_status', 'pending')->count(),
            'active_stores' => Store::count(),
        ]);
    }

    public function orders(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with('store', 'items');
        
        if ($request->query('store_id')) {
            $query->where('store_id', $request->query('store_id'));
        }
        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(50);
        return response()->json(['orders' => $orders]);
    }

    public function sellers(Request $request)
    {
        $this->authorize('viewAny', Store::class);

        $query = Store::with('owner');

        if ($request->query('verified')) {
            $verified = $request->query('verified') === 'true';
            $query->whereHas('verifications', fn($q) => $q->where('verification_status', $verified ? 'approved' : '!=', 'approved'));
        }

        $sellers = $query->orderBy('created_at', 'desc')->paginate(50);
        
        return response()->json(['sellers' => $sellers->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'owner' => $s->owner,
            'total_orders' => $s->orders()->count(),
            'total_revenue' => $s->orders()->sum('total'),
            'verified' => $s->verifications()->where('verification_status', 'approved')->exists(),
            'created_at' => $s->created_at,
        ])]);
    }

    public function payouts(Request $request)
    {
        $this->authorize('viewAny', Payout::class);

        $query = Payout::with('store');

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $payouts = $query->orderBy('created_at', 'desc')->paginate(50);
        
        return response()->json([
            'payouts' => $payouts,
            'summary' => [
                'total_pending' => Payout::where('status', 'pending')->sum('amount'),
                'total_processing' => Payout::where('status', 'processing')->sum('amount'),
                'total_completed' => Payout::where('status', 'completed')->sum('amount'),
                'total_failed' => Payout::where('status', 'failed')->count(),
            ]
        ]);
    }

    public function verifications(Request $request)
    {
        $this->authorize('viewAny', SellerVerification::class);

        $query = SellerVerification::with('user', 'store');

        if ($request->query('status')) {
            $query->where('verification_status', $request->query('status'));
        }

        $verifications = $query->orderBy('created_at', 'desc')->paginate(50);
        return response()->json(['verifications' => $verifications]);
    }
}
