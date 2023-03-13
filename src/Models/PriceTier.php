<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class PriceTier extends Model
{
    protected $fillable = [
        'name',
        'price',
        'product_id_google',
        'product_id_apple',
    ];

    protected $casts = [
        'price' => 'integer',
    ];

    public function skus()
    {
        return $this->hasMany(Sku::class);
    }
}
