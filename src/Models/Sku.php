<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sku extends Model
{
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saved(function (Sku $sku) {
            // Auto generate slug
            $sku->slug = Str::of("{$sku->id}-{$sku->title}")->slug();
            $sku->saveQuietly();
        });
    }

    protected $fillable = [
        'code',
        'title',
        'description',
        'is_active',
        'currency',
        'price',
        'exclusive_until',
        'is_bundle',
        'price_tier_id',
        'before_discount_tier_id',
        'image_id',
        'intro_media_id',
        'seq_no',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'integer',
        'exclusive_until' => 'datetime',
        'is_bundle' => 'boolean',
        'seq_no' => 'integer',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function activationCodes()
    {
        return $this->belongsToMany(ActivationCode::class)->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(
            get_class(
                config('auth.providers.users.model')
            )
        )->withTimestamps();
    }

    public function priceTier()
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    public function beforeDiscountTier()
    {
        return $this->belongsTo(PriceTier::class, 'before_discount_tier_id');
    }
}
