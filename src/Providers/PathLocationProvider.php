<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class PathLocationProvider extends AbstractLocationProvider
{
    public function provideLocation(UuidInterface $uuid, ServerRequestInterface $request): UriInterface
    {
        $uri = $request->getUri();
        $path = rtrim($uri->getPath(), '/');

        return $uri->withPath($path . '/' . $uuid->toString());
    }

    public function provideUuid(ServerRequestInterface $request): UuidInterface
    {
        $path = $request->getUri()->getPath();
        $parts = explode('/', $path);

        try {
            return Uuid::fromString($parts[array_key_last($parts)]);
        } catch (InvalidUuidStringException) {
            throw $this->getInvalidUuidException();
        }
    }
}
