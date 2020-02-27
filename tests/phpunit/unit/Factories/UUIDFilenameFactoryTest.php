<?php

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;
use SpazzMarticus\Tus\Factories\UUIDFilenameFactory;

class UUIDFilenameFactoryTest extends AbstractFilenameFactoryTest
{

    protected UUIDFilenameFactory $factory;

    public function setUp(): void
    {
        parent::setUp();
        $this->factory = new UUIDFilenameFactory($this->directory);
    }

    public function testGenerateFilename(): void
    {
        $metadata = [];

        $expectedFilename = new SplFileInfo($this->directory . $this->uuid->getHex());

        $this->assertEquals($expectedFilename, $this->factory->generateFilename($this->uuid, $metadata));
    }
}
