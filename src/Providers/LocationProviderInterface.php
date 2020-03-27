<?php

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\UuidInterface;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;
use Psr\Http\Message\UriInterface;

interface LocationProviderInterface
{
    /**
     * Provides a partial or full location string
     * @return UriInterface
     */
    public function provideLocation(UuidInterface $uuid, ServerRequestInterface $request): UriInterface;

    /**
     * Returns a valid UUID or throws a UnexpectedValueException
     * @return UuidInterface
     * @throws UnexpectedValueException
     */
    public function provideUuid(ServerRequestInterface $request): UuidInterface;
}
