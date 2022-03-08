<?php

namespace SpazzMarticus\Tus\Services;

use Mockery;
use Psr\Http\Message\RequestInterface;

class MetadataServiceTest extends \PHPUnit\Framework\TestCase
{
    protected MetadataService $metadataService;

    public function setUp(): void
    {
        $this->metadataService = new MetadataService();
    }

    /**
     * @dataProvider providerGetMetdata
     */
    public function testGetMetadata(RequestInterface $request, array $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->metadataService->getMetadata($request));
    }

    protected function mockRequest(string $header): RequestInterface
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getHeaderLine')
            ->once()
            ->with('Upload-Metadata')
            ->andReturn($header);
        return $request;
    }

    public function providerGetMetdata(): array
    {
        $dataSets = [];

        $request = $this->mockRequest('');

        $dataSets[] = [
            $request, []
        ];

        $request = $this->mockRequest('');

        $dataSets[] = [
            $request, []
        ];

        return $dataSets;
    }
}
