<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'sku_id',
        'description',
        'quantity',
        'currency',
        'amount',
        'discount',
        'total',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }
}
