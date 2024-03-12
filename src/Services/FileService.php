<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Services;

use Psr\Http\Message\StreamInterface;
use RuntimeException as BaseRuntimeException;
use SpazzMarticus\Tus\Exceptions\ConflictException;
use SpazzMarticus\Tus\Exceptions\RuntimeException;

final class FileService implements FileServiceInterface
{
    /**
     * Size of chunks to transfer from stream
     * @todo Give reasons for why this is important and test some more settings (with huge files)
     */
    private int $chunkSize = 1_048_576;

    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSize = $chunkSize;
    }

    public function create(string $filePath): void
    {
        if ($this->exists($filePath)) {
            throw new RuntimeException('File ' . $filePath . ' already exists');
        }

        if (touch($filePath) === false) {
            throw new RuntimeException('File ' . $filePath . ' could not be created');
        }
    }

    public function exists(string $filePath): bool
    {
        $pathname = $filePath;
        /**
         * Affected by status cache
         * @see https://www.php.net/manual/en/function.clearstatcache.php
         */
        clearstatcache(false, $pathname);

        return file_exists($pathname);
    }

    public function size(string $filePath): int
    {
        $pathname = $filePath;
        /**
         * Affected by status cache
         * @see https://www.php.net/manual/en/function.clearstatcache.php
         */
        clearstatcache(false, $pathname);

        return filesize($pathname) ?: 0;
    }

    public function delete(string $filePath): void
    {
        if (!$this->exists($filePath)) {
            return;
        }

        if (unlink($filePath)) {
            return;
        }

        throw new RuntimeException("Could not delete file");
    }

    public function open(string $filePath)
    {
        $resource = fopen($filePath, 'rb+');
        if (false === $resource) {
            throw new RuntimeException("Could not open file at " . $filePath);
        }

        return $resource;
    }

    public function point($handle, int $offset): void
    {
        if (fseek($handle, $offset) !== 0) {
            throw new RuntimeException('Can not set pointer in file');
        }
    }

    /**
     * @throws RuntimeException
     * @throws ConflictException if size limit is exceeded
     */
    public function copyFromStream($handle, StreamInterface $stream, ?int $sizeLimit = null): int
    {
        $bytesTransferred = 0;

        /**
         * Writing Input to Chunk
         * This in-between step is necessary for checking checksums
         * Reading input in chunks helps to support large files
         */
        while (!$stream->eof()) {
            try {
                $chunk = $stream->read($this->chunkSize);
            } catch (BaseRuntimeException $exception) {
                throw new RuntimeException("Error when reading stream", 0, $exception);
            }

            /**
             * Break iteration if chunk is empty
             */
            if ($chunk === "") {
                break;
            }

            $bytes = fwrite($handle, $chunk);

            if ($bytes === 0) {
                throw new RuntimeException("Error when writing file");
            }

            if (fflush($handle) === false) {
                throw new RuntimeException("Error when flushing file");
            }

            $bytesTransferred += $bytes;

            if ($sizeLimit && $bytesTransferred > $sizeLimit) {
                throw new ConflictException("Upload exceeds max allowed size");
            }
        }

        return $bytesTransferred;
    }
}
