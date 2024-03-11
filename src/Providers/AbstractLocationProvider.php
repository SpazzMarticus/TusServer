<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;

abstract class AbstractLocationProvider implements LocationProviderInterface
{
    protected function getInvalidUuidException(): UnexpectedValueException
    {
        return new UnexpectedValueException('No, or invalid uuid given!');
    }
}
