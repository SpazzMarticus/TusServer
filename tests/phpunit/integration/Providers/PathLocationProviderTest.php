<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Providers;

use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;

class PathLocationProviderTest extends AbstractLocationProviderTestCase
{
    protected PathLocationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new PathLocationProvider();
    }

    public function testProvideLocation(): void
    {
        $uuidString = '6e78f7aa-7e90-4f59-8701-ea925d340b5f';
        $uuid = Uuid::fromString($uuidString);

        $request = $this->getRequest(new Uri("https://www.example.org/path/to/application?param1=value1&param2&param3=value3"));

        $expectedUri = new Uri("https://www.example.org/path/to/application/6e78f7aa-7e90-4f59-8701-ea925d340b5f?param1=value1&param2&param3=value3");

        self::assertEquals($expectedUri, $this->provider->provideLocation($uuid, $request));
    }

    protected function mockServerRequestInterface(string $path): ServerRequestInterface
    {
        $uri = new Uri($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->method('getUri')
            ->willReturn($uri)
        ;

        return $request;
    }

    #[DataProvider('providerProvideUuid')]
    public function testProvideUuid(string $path, ?UuidInterface $uuid): void
    {
        $request = $this->mockServerRequestInterface($path);

        /**
         * Test for UUID or UnexpectedValueException
         */
        if ($uuid instanceof UuidInterface) {
            self::assertEquals($uuid, $this->provider->provideUuid($request));
        } else {
            $this->expectException(UnexpectedValueException::class);
            $this->provider->provideUuid($request);
        }
    }

    public static function providerProvideUuid(): \Iterator
    {
        $uuidString = '9cc981e6-4ebf-436a-a34d-0f2847d31685';
        $uuid = Uuid::fromString($uuidString);

        yield [
            '',
            null,
        ];
        yield [
            'path/this-is-not-a-valid-uuid',
            null,
        ];
        yield [
            'path/' . $uuidString,
            $uuid,
        ];
        yield [
            'this/is/a/longer/path/' . $uuidString,
            $uuid,
        ];
    }
}
