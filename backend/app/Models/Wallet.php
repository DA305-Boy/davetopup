<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'balance',
        'pending_payout',
        'currency'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'pending_payout' => 'decimal:2'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
