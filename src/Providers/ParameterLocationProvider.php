<?php

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ParameterLocationProvider extends AbstractLocationProvider implements LocationProviderInterface
{

    public function provideLocation(UuidInterface $uuid): string
    {
        return '?uuid=' . $uuid->toString();
    }

    public function provideUuid(ServerRequestInterface $request): UuidInterface
    {
        try {
            return Uuid::fromString($request->getQueryParams()['uuid'] ?? '');
        } catch (InvalidUuidStringException $exception) {
            $this->throwInvalid();
        }
    }
}
