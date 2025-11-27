<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'payload',
        'response_status',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'json',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const UPDATED_AT = null;

    /**
     * Scope: by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: successful webhooks
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('response_status', [200, 299]);
    }

    /**
     * Scope: failed webhooks
     */
    public function scopeFailed($query)
    {
        return $query->where('response_status', '>=', 400);
    }
}
