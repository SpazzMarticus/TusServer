<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Services;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class MetadataServiceTest extends TestCase
{
    protected MetadataService $metadataService;

    public function setUp(): void
    {
        $this->metadataService = new MetadataService();
    }

    /**
     * @param array<string, mixed> $expectedResult
     *
     * @dataProvider providerGetMetdata
     */
    public function testGetMetadata(RequestInterface $request, array $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->metadataService->getMetadata($request));
    }

    protected function mockRequest(string $header): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request
            ->expects($this->once())
            ->method('getHeaderLine')
            ->with('Upload-Metadata')
            ->willReturn($header)
        ;

        return $request;
    }

    /**
     * @return array<mixed>
     */
    public function providerGetMetdata(): array
    {
        $dataSets = [];

        $request = $this->mockRequest('');

        $dataSets[] = [
            $request, [],
        ];

        $request = $this->mockRequest('');

        $dataSets[] = [
            $request, [],
        ];

        return $dataSets;
    }
}
