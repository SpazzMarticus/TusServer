<?php

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;
use Ramsey\Uuid\UuidInterface;

class UUIDFilenameFactory implements TargetFileFactoryInterface
{

    protected $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function generateFilename(UuidInterface $uuid, array $metadata): SplFileInfo
    {
        return new SplFileInfo($this->directory . $uuid->getHex());
    }
}
