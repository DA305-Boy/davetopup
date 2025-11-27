<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'email',
        'phone',
        'player_uid',
        'player_nickname',
        'subtotal',
        'tax',
        'total',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get order items
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get transactions for this order
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope: pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        return in_array($this->status, ['payment_confirmed', 'delivered']);
    }

    /**
     * Get total items count
     */
    public function getTotalItemsCount(): int
    {
        return $this->items->sum('quantity');
    }
}
