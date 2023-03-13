<?php

namespace Omconnect\Pay\Traits;

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
}