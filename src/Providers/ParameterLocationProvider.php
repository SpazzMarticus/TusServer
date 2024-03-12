<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Psr\Http\Message\UriInterface;

class ParameterLocationProvider extends AbstractLocationProvider
{
    public function provideLocation(UuidInterface $uuid, ServerRequestInterface $request): UriInterface
    {
        $uri = $request->getUri();
        $uuidQuery = http_build_query(['uuid' => $uuid->toString()]);

        return $uri->withQuery($uri->getQuery() ? $uri->getQuery() . '&' . $uuidQuery : $uuidQuery);
    }

    public function provideUuid(ServerRequestInterface $request): UuidInterface
    {
        try {
            return Uuid::fromString($request->getQueryParams()['uuid'] ?? '');
        } catch (InvalidUuidStringException) {
            throw $this->getInvalidUuidException();
        }
    }
}
