<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserToken extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sso_user_id',
        'token',
        'expire_at',
        'ip_address',
        'device_id',
        'device_model',
        'os_version',
        'last_accessed',
        'fcm_token',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
        'last_accessed' => 'datetime',
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
