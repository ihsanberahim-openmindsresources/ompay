<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    const PLATFORM_GOOGLE = 'google';
    const PLATFORM_APPLE = 'apple';
    const PLATFORM_BACKEND = 'backend';
    const PLATFORM_STRIPE = 'stripe';
    const PLATFORM_REDEMPTION = 'redemption';

    protected $fillable = [
        'platform',
        'transaction_id',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'expires_date' => 'datetime',
        'cancellation_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            get_class(
                config('auth.providers.users.model')
            )
        );
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
