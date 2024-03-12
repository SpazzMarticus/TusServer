<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use SplFileInfo;

class OriginalFilenameFactoryTest extends AbstractFilenameFactoryTestCase
{
    protected OriginalFilenameFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new OriginalFilenameFactory($this->directory);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('providerGenerateFilename')]
    public function testGenerateFilename(string $expectedFilename, array $metadata): void
    {
        $expectedFilename = new SplFileInfo(sprintf('%s/%s', $this->directory, $expectedFilename));

        self::assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, $metadata));
    }

    public static function providerGenerateFilename(): \Iterator
    {
        /**
         * Prefer Name from $metadata['name']
         */
        yield [
            'my-filename.txt',
            [
                'name' => 'my-filename.txt',
                'filename' => 'my-other-filename.txt',
            ],
        ];
        /**
         * Use $metadata['filename'] if $metadata['name'] not available
         */
        yield [
            'my-other-filename.txt',
            [
                'filename' => 'my-other-filename.txt',
            ],
        ];
        /**
         * Fallback to UUID if no metadata present
         */
        yield [
            (string) Uuid::fromString('d9af80ad-44a1-445b-86dc-88b42a880d35')->getHex(),
            [],
        ];
    }

    public function testGenerateFilenameUsesUuidIfFileExists(): void
    {
        file_put_contents($this->directory . 'alreadyUploaded.bin', 'payload');

        $expectedFilename = new SplFileInfo($this->directory . $this->uuid->getHex());

        self::assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, [
            'name' => 'alreadyUploaded.bin',
        ]));
    }
}
