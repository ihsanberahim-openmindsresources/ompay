<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class ActivationCode extends Model
{
    protected $fillable = [
        'code',
        'available_seats',
        'total_seats',
        'order_id',
        'is_active',
        'activation_code_type_id',
        'discount_percent',
        'discount_value',
        'discount_limit',
        'product_id',
    ];

    protected $casts = [
        'available_seats' => 'integer',
        'total_seats' => 'integer',
        'is_active' => 'boolean',
        'discount_percent' => 'integer',
        'discount_value' => 'integer',
        'discount_limit' => 'integer',
    ];

    public function users()
    {
        return $this->belongsToMany(
            get_class(
                config('auth.providers.users.model')
            )
        )
        ->withTimestamps()
        ->withPivot(['discount_remaining']);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function skus()
    {
        return $this->belongsToMany(Sku::class)->withTimestamps();
    }

    public function activationCodeType()
    {
        return $this->belongsTo(ActivationCodeType::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
