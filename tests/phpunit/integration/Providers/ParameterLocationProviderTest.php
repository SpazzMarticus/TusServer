<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use Laminas\Diactoros\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;

final class ParameterLocationProviderTest extends AbstractLocationProviderTestCase
{
    private ParameterLocationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new ParameterLocationProvider();
    }

    public function testProvideLocationWithExistingQueryParams(): void
    {
        $uuidString = '6e78f7aa-7e90-4f59-8701-ea925d340b5f';
        $uuid = Uuid::fromString($uuidString);

        $request = $this->getRequest(new Uri("https://www.example.org/path/to/application?param1=value1&param2&param3=value3"));

        $expectedUri = new Uri("https://www.example.org/path/to/application?param1=value1&param2&param3=value3&uuid=6e78f7aa-7e90-4f59-8701-ea925d340b5f");

        self::assertEquals($expectedUri, $this->provider->provideLocation($uuid, $request));
    }

    public function testProvideLocationWithoutExistingQueryParams(): void
    {
        $uuidString = '6e78f7aa-7e90-4f59-8701-ea925d340b5f';
        $uuid = Uuid::fromString($uuidString);

        $request = $this->getRequest(new Uri("https://www.example.org/path/to/application"));

        $expectedUri = new Uri("https://www.example.org/path/to/application?uuid=6e78f7aa-7e90-4f59-8701-ea925d340b5f");

        self::assertEquals($expectedUri, $this->provider->provideLocation($uuid, $request));
    }

    /**
     * @param array<mixed> $queryParams
     */
    private function mockServerRequestInterface(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->method('getQueryParams')
            ->willReturn($queryParams)
        ;

        return $request;
    }

    public function testProvideValideUuid(): void
    {
        $uuidString = 'e3d54709-becf-46c0-9bcb-a28b5622edd3';
        $uuid = Uuid::fromString($uuidString);

        $request = $this->mockServerRequestInterface(['uuid' => $uuidString]);

        self::assertEquals($uuid, $this->provider->provideUuid($request));
    }

    public function testProvideInvalidUuid(): void
    {
        $request = $this->mockServerRequestInterface(['uuid' => 'this-will-definitly-not-work']);

        $this->expectException(UnexpectedValueException::class);
        $this->provider->provideUuid($request);
    }

    public function testProvideNoUuid(): void
    {
        $request = $this->mockServerRequestInterface([]);

        $this->expectException(UnexpectedValueException::class);
        $this->provider->provideUuid($request);
    }
}
