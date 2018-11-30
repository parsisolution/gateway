<?php

namespace Parsisolution\Gateway\Facades;

use Illuminate\Support\Facades\Facade;
use Parsisolution\Gateway\Contracts\Factory;

/**
 * @see \Parsisolution\Gateway\GatewayManager
 */
class Gateway extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }
}
