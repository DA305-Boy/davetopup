<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'external_id',
        'metadata',
        'is_default',
        'verified'
    ];

    protected $casts = [
        'metadata' => 'array',
        'verified' => 'boolean',
        'is_default' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayName()
    {
        $meta = $this->metadata ?? [];
        if ($this->type === 'card') {
            return 'Card •••• ' . ($meta['last4'] ?? '****');
        }
        return ucfirst($this->type) . ' (' . ($this->provider ?? 'local') . ')';
    }
}
