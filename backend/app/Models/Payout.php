<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'amount',
        'currency',
        'stripe_transfer_id',
        'status',
        'error_message',
        'processed_at',
        'retry_count',
        'next_retry_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
