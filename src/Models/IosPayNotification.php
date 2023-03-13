<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class IosPayNotification extends Model
{
    protected $fillable = [
        'environment',
        'notification_type',
        'auto_renew_product_id',
        'auto_renew_status',
        'auto_renew_status_change_date',
        'payload',
        'user_id',
    ];

    protected $casts = [
        'auto_renew_status_change_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            get_class(
                config('auth.providers.users.model')
            )
        );
    }
}
