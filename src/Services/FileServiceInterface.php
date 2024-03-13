<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Services;

use Psr\Http\Message\StreamInterface;

interface FileServiceInterface
{
    public function setChunkSize(int $chunkSize): void;

    public function create(string $filePath): void;

    public function exists(string $filePath): bool;

    public function size(string $filePath): int;

    public function delete(string $filePath): void;

    /**
     * @return resource
     */
    public function open(string $filePath);

    /**
     * @param resource $handle
     */
    public function point($handle, int $offset): void;

    /**
     * @param resource $handle
     */
    public function copyFromStream($handle, StreamInterface $stream, ?int $sizeLimit = null): int;
}
