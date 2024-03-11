<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;
use Ramsey\Uuid\UuidInterface;

interface FilenameFactoryInterface
{
    public function generateFilename(UuidInterface $uuid, array $metadata): SplFileInfo;
}
