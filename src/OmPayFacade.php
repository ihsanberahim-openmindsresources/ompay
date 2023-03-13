<?php

namespace Omconnect\Pay;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Openmindsresources\Payment\Skeleton\SkeletonClass
 */
class OmPayFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ompay';
    }
}
