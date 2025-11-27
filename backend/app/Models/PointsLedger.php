<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PointsLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'points_change',
        'reason',
        'order_id',
        'reward_redemption_id',
        'notes'
    ];

    protected $casts = [
        'points_change' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
