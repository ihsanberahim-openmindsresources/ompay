<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'sku_id',
        'email',
        'name',
        'transaction_id',
    ];

    public function activationCode()
    {
        return $this->hasOne(ActivationCode::class);
    }

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}
