<?php

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\UuidInterface;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;

interface LocationProviderInterface
{
    /**
     * Provides a partial or full location string
     * @return string
     */
    public function provideLocation(UuidInterface $uuid): string;

    /**
     * Returns a valid UUID or throws a UnexpectedValueException
     * @return UuidInterface
     * @throws UnexpectedValueException
     */
    public function provideUuid(ServerRequestInterface $request): UuidInterface;
}
