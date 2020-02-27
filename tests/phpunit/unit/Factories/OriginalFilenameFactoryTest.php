<?php

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;
use SpazzMarticus\Tus\Factories\OriginalFilenameFactory;

class OriginalFilenameFactoryTest extends AbstractFilenameFactoryTest
{

    protected OriginalFilenameFactory $factory;

    public function setUp(): void
    {
        parent::setUp();
        $this->factory = new OriginalFilenameFactory($this->directory);
    }
    /**
     * @dataProvider providerGenerateFilename
     */
    public function testGenerateFilename(string $expectedFilename, array $metadata): void
    {
        $expectedFilename = new SplFileInfo($expectedFilename);

        $this->assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, $metadata));
    }

    public function providerGenerateFilename(): array
    {
        parent::setUp();
        return [

            /**
             * Prefer Name from $metadata['name']
             */
            [
                $this->directory . 'my-filename.txt',
                [
                    'name' => 'my-filename.txt',
                    'filename' => 'my-other-filename.txt'
                ],
            ],
            /**
             * Use $metadata['filename'] if $metadata['name'] not available
             */
            [
                $this->directory . 'my-other-filename.txt',
                [
                    'filename' => 'my-other-filename.txt'
                ],
            ],
            /**
             * Fallback to UUID if no metadata present
             */
            [
                $this->directory . $this->uuid->getHex(),
                []
            ],
        ];
    }

    public function testGenerateFilenameUsesUuidIfFileExists(): void
    {
        file_put_contents($this->directory . 'alreadyUploaded.bin', 'payload');

        $expectedFilename = new SplFileInfo($this->directory . $this->uuid->getHex());

        $this->assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, [
            'name' => 'alreadyUploaded.bin'
        ]));
    }
}
