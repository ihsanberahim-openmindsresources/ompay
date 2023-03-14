<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    const TYPE_TOKENSUBSCRIPTION = 'token_subscription';
    const TYPE_TOKENPACK = 'token_pack';

    const UPGRADABLE = [
        Product::TYPE_TOKENSUBSCRIPTION => false,
        Product::TYPE_TOKENPACK => false,
    ];

    protected $fillable = [
        'title',
        'type',
        'product_id_google',
        'product_id_apple',
        'months',
        'is_active',
        'tokens',
    ];

    protected $casts = [
        'months' => 'integer',
        'tokens' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasManyThrough(
            config('auth.providers.users.model'),
            Subscription::class,
            'product_id', // this id
            'id', // users.id
            'id', // users.id ??
            'user_id', // subscriptions.user_id
        );
    }
}
