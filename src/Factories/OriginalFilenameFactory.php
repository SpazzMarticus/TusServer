<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;

final readonly class OriginalFilenameFactory implements FilenameFactoryInterface
{
    public function __construct(
        private string $directory,
    ) {}

    public function generateFilename(UuidInterface $uuid, array $metadata): string
    {
        $filename = $metadata['name'] ?? $metadata['filename'] ?? null;

        /**
         * Fallback to UUID if no $filename given, or file already exists
         */
        if (!$filename || file_exists($this->directory . $filename)) {
            $filename = $uuid->getHex();
        }

        return $this->directory . $filename;
    }
}
