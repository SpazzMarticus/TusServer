<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;

final readonly class UuidFilenameFactory implements FilenameFactoryInterface
{
    public function __construct(
        private string $directory,
    ) {}

    /**
     * @param array{name?: ?string, filename?: ?string} $metadata
     */
    public function generateFilename(UuidInterface $uuid, array $metadata): string
    {
        return $this->directory . $uuid->getHex();
    }
}
