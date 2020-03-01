<?php

namespace SpazzMarticus\Tus\Providers;

use Ramsey\Uuid\Uuid;
use Mockery;
use Psr\Http\Message\ServerRequestInterface;
use SpazzMarticus\Tus\Exceptions\LogicException;

class ParameterLocationProviderTest extends AbstractLocationProviderTest
{

    protected ParameterLocationProvider $provider;

    public function setUp(): void
    {
        $this->provider = new ParameterLocationProvider();
    }

    public function testProvideLocation()
    {
        $uuidString = '6e78f7aa-7e90-4f59-8701-ea925d340b5f';
        $uuid = Uuid::fromString($uuidString);

        $this->assertSame('?uuid=' . $uuidString, $this->provider->provideLocation($uuid));
    }

    protected function mockServerRequestInterface(array $queryParams): ServerRequestInterface
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->allows()
            ->getQueryParams()
            ->andReturn($queryParams);

        return $request;
    }

    public function testProvideValideUuid()
    {
        $uuidString = 'e3d54709-becf-46c0-9bcb-a28b5622edd3';
        $uuid = Uuid::fromString($uuidString);

        $request = $this->mockServerRequestInterface(['uuid' => $uuidString]);

        $this->assertEquals($uuid, $this->provider->provideUuid($request));
    }

    public function testProvideInvalidUuid()
    {
        $request = $this->mockServerRequestInterface(['uuid' => 'this-will-definitly-not-work']);

        $this->expectException(LogicException::class);
        $this->provider->provideUuid($request);
    }

    public function testProvideNoUuid()
    {
        $request = $this->mockServerRequestInterface([]);

        $this->expectException(LogicException::class);
        $this->provider->provideUuid($request);
    }
}