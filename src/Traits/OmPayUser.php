<?php

namespace Omconnect\Pay\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Omconnect\Pay\Models\Sku;
use Omconnect\Pay\Models\TokenTransaction;

trait OmPayUser{
    // Get transactions
    private function _getTransactionsCache()
    {
        return Cache::tags(['transactions', "user:{$this->id}"]);
    }

    public function resetTransactionsCache()
    {
        $this->_getTransactionsCache()->flush();
    }

    public function tokenTransactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }

    public function skus()
    {
        return $this->belongsToMany(Sku::class)->withTimestamps();
    }

    private function _getSubscriptionCache()
    {
        return Cache::tags(['subscription', "user:{$this->id}"]);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()
            ->whereHas('product')
            ->where('expires_date', '>', Carbon::now())
            ->orderBy('expires_date', 'desc')->first();
    }

    public function activeSubscriptions()
    {
        return $this->subscriptions()
            ->whereHas('product')
            ->where('expires_date', '>', Carbon::now())
            ->orderBy('expires_date', 'desc')
            ->get();
    }

    public function hasActiveSubscription()
    {
        $is_active = $this->_getSubscriptionCache()->get('active');
        if ($is_active === null) {
            $subscription = $this->activeSubscription();
            if ($subscription) {
                $this->_getSubscriptionCache()->put('active', true, $subscription->expires_date->diffInSeconds(Carbon::now()));
                return true;
            }
            $this->_getSubscriptionCache()->put('active', false, 86400);
        }
        return $is_active;
    }
}