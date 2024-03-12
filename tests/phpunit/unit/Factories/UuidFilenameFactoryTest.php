<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;

class UuidFilenameFactoryTest extends AbstractFilenameFactoryTestCase
{
    protected UuidFilenameFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new UuidFilenameFactory($this->directory);
    }

    public function testGenerateFilename(): void
    {
        $metadata = [];

        $expectedFilename = new SplFileInfo($this->directory . $this->uuid->getHex());

        self::assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, $metadata));
    }
}
