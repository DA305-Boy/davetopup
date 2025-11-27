<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\PointsService;

class RewardController extends Controller
{
    protected $pointsService;

    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    // List active rewards (optionally by store)
    public function index(Request $request)
    {
        $storeId = $request->query('store_id');
        $query = Reward::where('active', true);
        if ($storeId) $query->where('store_id', $storeId);
        return response()->json(['rewards' => $query->get()]);
    }

    // Admin create reward
    public function store(Request $request)
    {
        $this->authorize('create', Reward::class);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points_required' => 'required|integer|min:0',
            'store_id' => 'nullable|integer|exists:stores,id'
        ]);

        $reward = Reward::create($request->only(['title','description','points_required','store_id','active']));
        return response()->json(['reward' => $reward], 201);
    }

    // Redeem a reward for a user (creates redemption record)
    public function redeem(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'User must be authenticated'], 401);
        }

        try {
            // Check if user has enough points
            $pointsInfo = $this->pointsService->getPointsInfo($user->id);
            
            if ($pointsInfo['balance'] < $reward->points_required) {
                return response()->json([
                    'error' => 'Insufficient points',
                    'required' => $reward->points_required,
                    'available' => $pointsInfo['balance']
                ], 422);
            }

            // Deduct points from user
            $this->pointsService->deductPoints(
                $user->id,
                $reward->points_required,
                'reward_redemption',
                null,
                null,
                "Redeemed reward: {$reward->title}"
            );

            // Create redemption record
            $redemption = RewardRedemption::create([
                'reward_id' => $reward->id,
                'user_id' => $user->id,
                'order_id' => $request->input('order_id', null),
                'status' => 'completed',
                'points_deducted' => $reward->points_required,
            ]);

            return response()->json(['redemption' => $redemption], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
