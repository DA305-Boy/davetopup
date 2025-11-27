<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RewardRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'reward_id',
        'user_id',
        'order_id',
        'status'
    ];

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
