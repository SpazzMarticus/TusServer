<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;
use SplFileInfo;

interface FilenameFactoryInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function generateFilename(UuidInterface $uuid, array $metadata): SplFileInfo;
}
