<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPoints extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'lifetime_earned',
        'lifetime_redeemed'
    ];

    protected $casts = [
        'balance' => 'integer',
        'lifetime_earned' => 'integer',
        'lifetime_redeemed' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
