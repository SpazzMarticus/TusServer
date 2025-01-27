<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Services;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class MetadataServiceTest extends TestCase
{
    private MetadataService $metadataService;

    protected function setUp(): void
    {
        $this->metadataService = new MetadataService();
    }

    public function testGetMetadata(): void
    {
        $request = $this->mockRequest('');

        self::assertSame([], $this->metadataService->getMetadata($request));
    }

    private function mockRequest(string $header): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request
            ->expects(self::once())
            ->method('getHeaderLine')
            ->with('Upload-Metadata')
            ->willReturn($header)
        ;

        return $request;
    }
}
