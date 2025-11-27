<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'token',
        'title',
        'description',
        'amount',
        'currency',
        'items',
        'status',
        'expires_at'
    ];

    protected $casts = [
        'items' => 'array',
        'amount' => 'decimal:2',
        'expires_at' => 'datetime'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
