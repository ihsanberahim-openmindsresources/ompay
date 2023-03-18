<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public const STATUS_CANCEL = -1;
    public const STATUS_PENDING = 0;
    public const STATUS_SUCCESS = 1;

    protected $fillable = [
        'user_id',
        'activation_code_id',
        'stripe_session_id',
        'currency',
        'total_value',
        'status',
    ];

    //
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function activationCode()
    {
        return $this->belongsTo(ActivationCode::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payloads()
    {
        return $this->hasMany(TransactionPayload::class);
    }
}
