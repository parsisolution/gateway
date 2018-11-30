<?php

namespace Parsisolution\Gateway\Contracts;

interface Factory
{
    /**
     * Get an Gateway provider implementation.
     *
     * @param  string $driver
     * @return \Parsisolution\Gateway\Contracts\Provider
     */
    public function driver($driver = null);
}
