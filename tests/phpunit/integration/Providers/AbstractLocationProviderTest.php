<?php

namespace SpazzMarticus\Tus\Providers;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractLocationProviderTest extends TestCase
{

    protected function getRequest(UriInterface $uri): ServerRequestInterface
    {
        parse_str($uri->getQuery(), $params);
        return new ServerRequest([], [], $uri, null, 'php://input', [], [], $params);
    }
}
