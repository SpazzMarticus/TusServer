<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;

interface FilenameFactoryInterface
{
    /**
     * @param array{name?: ?string, filename?: ?string} $metadata
     */
    public function generateFilename(UuidInterface $uuid, array $metadata): string;
}
