<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'points_required',
        'store_id',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
