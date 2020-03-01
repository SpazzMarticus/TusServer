<?php

namespace SpazzMarticus\Tus\Services;

use SpazzMarticus\Tus\Exceptions\RuntimeException;
use SplFileInfo;

/**
 * @todo Use SplFileObject instead of f*-file-functions?
 */
final class FileService
{
    public function instance(string $path): SplFileInfo
    {
        return new SplFileInfo($path);
    }

    public function create(SplFileInfo $file): void
    {
        if (file_put_contents($file->getPathname(), '') === false) {
            throw new RuntimeException('File ' . $file->getPathname() . ' could not be created');
        }
    }

    public function exists(SplFileInfo $file): bool
    {
        $pathname = $file->getPathname();
        /**
         * Affected by status cache
         * @see https://www.php.net/manual/en/function.clearstatcache.php
         */
        clearstatcache(false, $pathname);
        return file_exists($pathname);
    }

    public function size(SplFileInfo $file): int
    {
        $pathname = $file->getPathname();
        /**
         * Affected by status cache
         * @see https://www.php.net/manual/en/function.clearstatcache.php
         */
        clearstatcache(false, $pathname);
        return filesize($pathname) ?: 0;
    }

    public function delete(SplFileInfo $file): void
    {
        if ($this->exists($file)) {
            if (!unlink($file->getPathname())) {
                if ($this->exists($file)) {
                    /**
                     * @todo Not handled in TusServer
                     */
                    throw new RuntimeException("Could not delete file");
                }
            }
        }
    }

    /**
     * @return resource
     */
    public function open(SplFileInfo $file)
    {
        $handle = fopen($file->getPathname(), 'rb+');
        if (!$handle) {
            throw new RuntimeException('Can not open file ' . $file->getPathname());
        }
        return $handle;
    }

    /**
     * @param resource $handle
     */
    public function point($handle, int $offset): void
    {
        if (fseek($handle, $offset) !== 0) {
            throw new RuntimeException('Can not set pointer in file');
        }
    }
    /**
     * @param resource $handle
     */
    public function read($handle, int $length): string
    {
        return fread($handle, $length);
    }

    /**
     * @param resource $handle
     */
    public function write($handle, string $data): int
    {
        $bytes = fwrite($handle, $data);

        if ($bytes === false || $bytes !== strlen($data)) {
            throw new RuntimeException("Write error");
        }

        return $bytes;
    }


    /**
     * @param resource $handle
     */
    public function eof($handle): bool
    {
        return feof($handle);
    }

    /**
     * @param resource $to
     */
    public function copy($from, $to): int
    {
        $bytes = stream_copy_to_stream($from, $to);
        if ($bytes === false) {
            throw new RuntimeException('Failed to copy stream');
        }
        return $bytes;
    }

    /**
     * @param resource $handle
     */
    public function flush($handle): void
    {
        if (!fflush($handle)) {
            throw new RuntimeException("Flush error");
        }
    }

    /**
     * @param resource $handle
     */
    public function close($handle): void
    {
        /**
         * @todo Check for FALSE?
         * @see Comments on https://www.php.net/manual/de/function.fclose
         */
        fclose($handle);
    }
}
