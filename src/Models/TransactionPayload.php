<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionPayload extends Model
{
    protected $fillable = [
        'transaction_id',
        'payload',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
