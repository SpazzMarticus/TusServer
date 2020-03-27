<?php

namespace SpazzMarticus\Tus\Providers;

use Ramsey\Uuid\Uuid;
use Mockery;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Ramsey\Uuid\UuidInterface;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;
use Laminas\Diactoros\Uri;
use Laminas\Diactoros\ServerRequest;

class PathLocationProviderTest extends AbstractLocationProviderTest
{

    protected PathLocationProvider $provider;

    public function setUp(): void
    {
        $this->provider = new PathLocationProvider();
        parent::setUp();
    }

    public function testProvideLocation(): void
    {
        $uuidString = '6e78f7aa-7e90-4f59-8701-ea925d340b5f';
        $uuid = Uuid::fromString($uuidString);

        $request = $this->getRequest(new Uri("https://www.example.org/path/to/application?param1=value1&param2&param3=value3"));

        $expectedUri = new Uri("https://www.example.org/path/to/application/6e78f7aa-7e90-4f59-8701-ea925d340b5f?param1=value1&param2&param3=value3");

        $this->assertEquals($expectedUri, $this->provider->provideLocation($uuid, $request));
    }

    protected function mockServerRequestInterface(string $path): ServerRequestInterface
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getUri->getPath')
            ->andReturn($path);

        return $request;
    }

    /**
     * @dataProvider providerProvideUuid
     */
    public function testProvideUuid(ServerRequestInterface $request, ?UuidInterface $uuid): void
    {
        /**
         * Test for UUID or UnexpectedValueException
         */
        if ($uuid) {
            $this->assertEquals($uuid, $this->provider->provideUuid($request));
        } else {
            $this->expectException(UnexpectedValueException::class);
            $this->provider->provideUuid($request);
        }
    }

    public function providerProvideUuid(): array
    {
        $uuidString = '9cc981e6-4ebf-436a-a34d-0f2847d31685';
        $uuid = Uuid::fromString($uuidString);
        return [
            [
                $this->mockServerRequestInterface(''),
                null
            ],
            [
                $this->mockServerRequestInterface('path/this-is-not-a-valid-uuid'),
                null
            ],
            [
                $this->mockServerRequestInterface('path/' . $uuidString),
                $uuid
            ],
            [
                $this->mockServerRequestInterface('this/is/a/longer/path/' . $uuidString),
                $uuid
            ],
        ];
    }
}
