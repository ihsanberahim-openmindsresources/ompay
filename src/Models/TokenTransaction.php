<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TokenTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'referenceable_type',
        'referenceable_id',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($transaction) {
            // Refresh
            $transaction->user->getTokens();
        });
        static::deleting(function ($transaction) {
            // Refresh
            $transaction->user->getTokens();
        });
        static::created(function ($transaction) {
            $transaction->user->updateTokens($transaction->value);
        });
        static::deleted(function ($transaction) {
            $transaction->user->updateTokens($transaction->value * -1);
        });
    }

    public function user()
    {
        return $this->belongsTo(
            get_class(
                config('auth.providers.users.model')
            )
        );
    }

    public function referenceable()
    {
        return $this->morphTo();
    }
}
