<?php

namespace SpazzMarticus\Tus;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
use SpazzMarticus\Tus\Factories\UuidFilenameFactory;
use SpazzMarticus\Tus\Providers\LocationProviderInterface;
use SpazzMarticus\Tus\Providers\PathLocationProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TusServerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private FilesystemCachePool $cache;
    private EventDispatcherInterface $dispatcher;
    private LocationProviderInterface $locationProvider;
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        vfsStream::setup('root', null, [
            'uploads' => []
        ]);
        $this->directory = vfsStream::url('root/uploads/');
        $this->fileNameFactory = new UuidFilenameFactory($this->directory);
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
        $filesystem = new Filesystem(new Local("/tmp"));
        $this->cache = new FilesystemCachePool($filesystem);
        $this->dispatcher = new EventDispatcher();
        $this->locationProvider = new PathLocationProvider();
    }

    public function testWithProvideAnUuidFactoryInstance(): void
    {
        $stub = $this->createStub(UuidFactoryInterface::class);
        $stub->method('uuid4')
            ->willReturn(Uuid::fromString('00000000-0000-0000-0000-000000000000'));

        $tusServer = new TusServer(
            $this->responseFactory,
            $this->streamFactory,
            $this->cache,
            $this->dispatcher,
            $this->fileNameFactory,
            $this->locationProvider,
            $stub
        );
        $requestFactory = new ServerRequestFactory();
        $serverRequest = $requestFactory->createServerRequest("POST", "/files");
        $serverRequest = $serverRequest->withHeader('Tus-Resumable', '1.0.0')
            ->withHeader('Upload-Length', '1000')
            ->withHeader('Upload-Metadata', 'filename d29ybGRfZG9taW5hdGlvbl9wbGFuLnBkZg==');

        $response = $tusServer->handle($serverRequest);
        self::assertEquals(201, $response->getStatusCode());
        self::assertStringEndsWith('/00000000-0000-0000-0000-000000000000', $response->getHeaderLine('Location'));
    }

    public function testWithoutUuidFactory(): void
    {
        $tusServer = new TusServer(
            $this->responseFactory,
            $this->streamFactory,
            $this->cache,
            $this->dispatcher,
            $this->fileNameFactory,
            $this->locationProvider
        );
        $requestFactory = new ServerRequestFactory();
        $serverRequest = $requestFactory->createServerRequest("POST", "/files");
        $serverRequest = $serverRequest->withHeader('Tus-Resumable', '1.0.0')
            ->withHeader('Upload-Length', '1000')
            ->withHeader('Upload-Metadata', 'filename d29ybGRfZG9taW5hdGlvbl9wbGFuLnBkZg==');

        $response = $tusServer->handle($serverRequest);
        self::assertEquals(201, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '/\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response->getHeaderLine('Location')
        );
    }
}
