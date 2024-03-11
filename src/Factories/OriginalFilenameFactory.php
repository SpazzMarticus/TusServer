<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;
use SplFileInfo;

class OriginalFilenameFactory implements FilenameFactoryInterface
{
    public function __construct(protected string $directory) {}

    public function generateFilename(UuidInterface $uuid, array $metadata): SplFileInfo
    {
        $filename = $metadata['name'] ?? $metadata['filename'] ?? null;

        /**
         * Fallback to UUID if no $filename given, or file already exists
         */
        if (!$filename || file_exists($this->directory . $filename)) {
            $filename = $uuid->getHex();
        }

        return new SplFileInfo($this->directory . $filename);
    }
}
