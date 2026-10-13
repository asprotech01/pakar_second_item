<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketValue extends Model
{
    protected $fillable = [
        'device_id',
        'storage_variant',
        'condition',
        'market_price_min',
        'market_price_max',
        'source',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'market_price_min' => 'decimal:2',
            'market_price_max' => 'decimal:2',
            'observed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
