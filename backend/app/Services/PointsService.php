<?php

namespace App\Services;

use App\Models\UserPoints;
use App\Models\PointsLedger;

class PointsService
{
    /**
     * Award points to user (on purchase, referral, etc)
     */
    public function awardPoints(int $userId, int $points, string $reason, ?int $orderId = null, ?string $notes = null): UserPoints
    {
        $userPoints = UserPoints::firstOrCreate(['user_id' => $userId]);
        
        $userPoints->increment('balance', $points);
        $userPoints->increment('lifetime_earned', $points);

        PointsLedger::create([
            'user_id' => $userId,
            'points_change' => $points,
            'reason' => $reason,
            'order_id' => $orderId,
            'notes' => $notes
        ]);

        return $userPoints;
    }

    /**
     * Deduct points (on redemption, etc)
     */
    public function deductPoints(int $userId, int $points, string $reason, ?int $rewardId = null, ?string $notes = null): UserPoints
    {
        $userPoints = UserPoints::firstOrCreate(['user_id' => $userId]);

        if ($userPoints->balance < $points) {
            throw new \Exception('Insufficient points balance');
        }

        $userPoints->decrement('balance', $points);
        $userPoints->increment('lifetime_redeemed', $points);

        PointsLedger::create([
            'user_id' => $userId,
            'points_change' => -$points,
            'reason' => $reason,
            'reward_redemption_id' => $rewardId,
            'notes' => $notes
        ]);

        return $userPoints;
    }

    /**
     * Get user points info
     */
    public function getPointsInfo(int $userId)
    {
        $userPoints = UserPoints::firstOrCreate(['user_id' => $userId]);
        
        return [
            'balance' => $userPoints->balance,
            'lifetime_earned' => $userPoints->lifetime_earned,
            'lifetime_redeemed' => $userPoints->lifetime_redeemed,
            'next_milestone' => (int)ceil($userPoints->balance / 100) * 100
        ];
    }

    /**
     * Get points ledger for user
     */
    public function getLedger(int $userId, int $limit = 50)
    {
        return PointsLedger::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
