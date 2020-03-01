<?php

namespace SpazzMarticus\Tus\Factories;

use Ramsey\Uuid\UuidInterface;
use SplFileInfo;

class OriginalFilenameFactory implements FilenameFactoryInterface
{

    protected $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

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
