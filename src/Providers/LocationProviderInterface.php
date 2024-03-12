<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\UuidInterface;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;

interface LocationProviderInterface
{
    /**
     * Provides a partial or full location string
     */
    public function provideLocation(UuidInterface $uuid, ServerRequestInterface $request): UriInterface;

    /**
     * Returns a valid UUID or throws a UnexpectedValueException
     * @throws UnexpectedValueException
     */
    public function provideUuid(ServerRequestInterface $request): UuidInterface;
}
