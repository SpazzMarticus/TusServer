<?php

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PathLocationProvider extends AbstractLocationProvider implements LocationProviderInterface
{

    public function provideLocation(UuidInterface $uuid): string
    {
        return $uuid->toString();
    }

    public function provideUuid(ServerRequestInterface $request): UuidInterface
    {
        $path = $request->getUri()->getPath();
        $parts = explode('/', $path);

        try {
            return Uuid::fromString($parts[array_key_last($parts)]);
        } catch (InvalidUuidStringException $exception) {
            $this->throwInvalid();
        }
    }
}
