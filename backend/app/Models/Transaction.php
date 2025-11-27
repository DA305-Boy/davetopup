<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_id',
        'payment_method',
        'amount',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order this transaction belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope: successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get human-readable payment method
     */
    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'card' => 'Credit/Debit Card',
            'paypal' => 'PayPal',
            'binance' => 'Binance Pay',
            'voucher' => 'Gift Card/Voucher',
            default => ucfirst($this->payment_method),
        };
    }

    /**
     * Check if transaction requires action
     */
    public function requiresAction(): bool
    {
        return in_array($this->status, [
            'requires_3d_secure',
            'requires_verification',
            'pending',
        ]);
    }
}
