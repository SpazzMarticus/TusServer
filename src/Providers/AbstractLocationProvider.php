<?php

namespace SpazzMarticus\Tus\Providers;

use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;

abstract class AbstractLocationProvider implements LocationProviderInterface{

    /**
     * @throws UnexpectedValueException
     */
    protected function throwInvalid()
    {
        throw new UnexpectedValueException('No, or invalid uuid given!');
    }

}