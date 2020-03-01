<?php

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;
use SpazzMarticus\Tus\Factories\UuidFilenameFactory;

class UuidFilenameFactoryTest extends AbstractFilenameFactoryTest
{

    protected UuidFilenameFactory $factory;

    public function setUp(): void
    {
        parent::setUp();
        $this->factory = new UuidFilenameFactory($this->directory);
    }

    public function testGenerateFilename(): void
    {
        $metadata = [];

        $expectedFilename = new SplFileInfo($this->directory . $this->uuid->getHex());

        $this->assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, $metadata));
    }
}
