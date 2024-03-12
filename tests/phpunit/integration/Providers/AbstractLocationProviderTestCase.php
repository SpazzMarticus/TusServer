<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

abstract class AbstractLocationProviderTestCase extends TestCase
{
    protected function getRequest(UriInterface $uri): ServerRequestInterface
    {
        parse_str($uri->getQuery(), $params);

        return new ServerRequest([], [], $uri, null, 'php://input', [], [], $params);
    }
}
