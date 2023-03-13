<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    const PLATFORM_GOOGLE = 'google';
    const PLATFORM_APPLE = 'apple';
    const PLATFORM_BACKEND = 'backend';
    const PLATFORM_STRIPE = 'stripe';
    const PLATFORM_REDEMPTION = 'redemption';

    const STATUS_CANCELLED = -1;
    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;

    protected $fillable = [
        'platform',
        'user_id',
        'sku_id',
        'price_tier_id',
        'product_id',
        'status',
    ];

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }

    public function priceTier()
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
