<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class AndroidPayNotification extends Model
{
    protected $fillable = [
        'notification_type',
        'auto_renew_product_id',
        'payload',
        'user_id',
    ];

    protected $casts = [];

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
