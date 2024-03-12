<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;
use SplFileInfo;

class UuidFilenameFactory implements FilenameFactoryInterface
{
    public function __construct(protected string $directory) {}

    public function generateFilename(UuidInterface $uuid, array $metadata): SplFileInfo
    {
        return new SplFileInfo($this->directory . $uuid->getHex());
    }
}
