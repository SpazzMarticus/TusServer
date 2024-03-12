<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;

interface FilenameFactoryInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function generateFilename(UuidInterface $uuid, array $metadata): string;
}
