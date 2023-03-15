<?php

namespace Omconnect\Pay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Omconnect\Pay\Models\Subscription;

class AfterProcessSubscription{

    use Dispatchable, SerializesModels;

    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}