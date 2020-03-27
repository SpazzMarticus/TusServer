<?php

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Psr\Http\Message\UriInterface;

class PathLocationProvider extends AbstractLocationProvider implements LocationProviderInterface
{

    public function provideLocation(UuidInterface $uuid, ServerRequestInterface $request): UriInterface
    {
        $uri = $request->getUri();
        return $uri->withPath($uri->getPath() . '/' . $uuid->toString());
    }

    public function provideUuid(ServerRequestInterface $request): UuidInterface
    {
        $path = $request->getUri()->getPath();
        $parts = explode('/', $path);

        try {
            return Uuid::fromString($parts[array_key_last($parts)]);
        } catch (InvalidUuidStringException $exception) {
            throw $this->getInvalidUuidException();
        }
    }
}
