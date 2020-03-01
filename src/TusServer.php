<?php

namespace SpazzMarticus\Tus;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SpazzMarticus\Tus\Events\UploadComplete;
use SpazzMarticus\Tus\Events\UploadStarted;
use SpazzMarticus\Tus\Exceptions\ConflictException;
use SpazzMarticus\Tus\Exceptions\UnexpectedValueException;
use SpazzMarticus\Tus\Exceptions\RuntimeException;
use SpazzMarticus\Tus\Factories\FilenameFactoryInterface;
use SpazzMarticus\Tus\Providers\LocationProviderInterface;
use SplFileInfo;
use Throwable;

/**
 * @todo Add suggestions for installation of (tested?) PSR-implementations to composer.json
 * @todo Check for extensions or MIME? (Extension like TargetFileFactory?)
 */
class TusServer implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const SUPPORTED_VERSIONS = ['1.0.0'];

    /**
     * PSR-Dependencies
     */
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;
    protected CacheInterface $storage;
    protected EventDispatcherInterface $eventDispatcher;
    /**
     * Package-Dependencies
     */
    protected FilenameFactoryInterface $targetFileFactory;
    protected LocationProviderInterface $locationProvider;

    /**
     * Size Settings
     */
    protected int $maxSize = 1_073_741_824;
    protected int $chunkSize = 1_048_576;

    /**
     * Settings for GET-calls
     */
    protected bool $allowGetCalls = false;
    protected ?int $storageTTLAfterUploadComplete = -1;
    protected bool $allowGetCallsForPartialUploads = false;

    /**
     * Settings for using intermediate chunks
     */
    protected bool $useIntermediateChunk = false;
    protected string $chunkDirectory = '';

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        CacheInterface $storage,
        EventDispatcherInterface $eventDispatcher,
        FilenameFactoryInterface $targetFileFactory,
        LocationProviderInterface $locationProvider
    ) {
        $this->logger = new NullLogger();
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->storage = $storage;
        $this->eventDispatcher = $eventDispatcher;
        $this->targetFileFactory = $targetFileFactory;
        $this->locationProvider = $locationProvider;
    }

    /**
     * Implemented for future implementation of Checksum-Extension as defined in tus.io-Protocol.
     * (Since Uppy does not support it (yet?), this will proably be added later on.)
     */
    public function setUseIntermediateChunk(bool $use, string $chunkDirectory = null): self
    {
        $this->useIntermediateChunk = $use;
        $this->chunkDirectory = $chunkDirectory ?? sys_get_temp_dir() . '/';
        return $this;
    }
    /**
     * Limits the max size of the upload
     */
    public function setMaxSize(int $maxSize): self
    {
        $this->maxSize = $maxSize;
        return $this;
    }
    /**
     * Limits the size read from the request and written to target file (or intermediate chunk file)
     * (The upload is not split in multiple files.)
     * @todo Give reasons for why this is important and test some more settings (with huge files)
     * @param int $chunkSize
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;
        return $this;
    }

    /**
     * Serves uploaded file on GET-calls
     * (not part of tus.io-Protocol)
     * @param bool $allow
     * @param int $ttl Restricts calls by time to live, ticking from completion of the upload
     * @param bool $allowPartial Restricts calls to complete files
     */
    public function setAllowGetCalls(bool $allow, int $ttl = null, bool $allowPartial = false): self
    {
        $this->allowGetCalls = $allow;
        $this->storageTTLAfterUploadComplete = $allow ? $ttl : -1;
        $this->allowGetCallsForPartialUploads = $allow && $allowPartial;
        return $this;
    }

    /**
     *
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $clientVersion = $this->getHeaderScalar($request, 'Tus-Resumable');

        if (!in_array($clientVersion, self::SUPPORTED_VERSIONS)) {
            return $this->createResponse(412); //Precondition Failed
        }

        $method = $this->getHeaderScalar($request, 'X-HTTP-Method-Override') ?? $request->getMethod();

        switch ($method) {
            case 'OPTIONS':
                return $this->handleOptions($request);
                break;
            case 'HEAD':
                return $this->handleHead($request);
                break;
            case 'POST':
                return $this->handlePost($request);
                break;
            case 'PATCH':
                return $this->handlePatch($request);
                break;
            case 'GET':
                return $this->handleGet($request);
                break;
        }
        return $this->createResponse(400); //Bad Request
    }

    protected function handleOptions(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createResponse(200)
            ->withHeader('Tus-Version', '1.0.0')
            ->withHeader('Tus-Max-Size', (string) $this->maxSize)
            ->withHeader('Tus-Extension', 'creation, creation-defer-length, creation-with-upload');
    }

    protected function handleHead(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $uuid = $this->locationProvider->provideUuid($request);
        } catch (UnexpectedValueException $t) {
            return $this->createResponse(404);
        }

        $storage = $this->storage->get($uuid->getHex());

        if (!$storage) {
            return $this->createResponse(404);
        }

        $targetFile = $this->createFile($storage['file']);
        $size = $this->getFileSize($targetFile);

        $response = $this->createResponse(200)
            ->withHeader('Upload-Offset', (string) $size);

        if (!$storage['defer']) {
            $response = $response->withHeader('Upload-Length', $storage['length']);
        }

        return $response;
    }

    protected function handlePost(ServerRequestInterface $request): ResponseInterface
    {
        $length = (int) $this->getHeaderScalar($request, 'Upload-Length') ?: 0;
        $defer = false;

        if (!$length) {
            $defer = $this->getHeaderScalar($request, 'Upload-Defer-Length') === "1";

            if (!$defer) {
                return $this->createResponse(400); //Bad request
            }
        } elseif ($length > $this->maxSize) {
            return $this->createResponse(413); //Request Entity Too Large
        }

        $uuid = Uuid::uuid4();

        $metadata = $this->getMetadata($request);

        $targetFile = $this->targetFileFactory->generateFilename($uuid, $metadata);

        if (!is_dir($targetFile->getPath())) {
            throw new RuntimeException($targetFile->getPath() . ' is not a directory');
        }

        $storage = [
            'complete' => false,
            'length' => $length,
            'defer' => $defer,
            'metadata' => $metadata,
            'file' => $targetFile->getPathname(),
        ];
        $this->storage->set($uuid->getHex(), $storage);

        if (file_put_contents($targetFile->getPathname(), '') === false) {
            $this->storage->delete($uuid->getHex());
            throw new RuntimeException('File ' . $targetFile->getPathname() . ' could not be created');
        }

        //Created
        /**
         * @todo LocationProviderInterface?!
         * @todo LocationExtractorInterface?!
         * Ein Interface mit beiden funktionen wäre besser
         */
        $response = $this->createResponse(201)
            ->withHeader('Location', $this->locationProvider->provideLocation($uuid));

        if ($defer) {
            $response = $response->withHeader('Upload-Defer-Length', "1");
        }

        if ($this->getHeaderScalar($request, 'Content-Type')  === 'application/offset+octet-stream') {
            return $this->handlePatch($request, $response, $uuid);
        } else {
            return $response->withHeader('Upload-Offset', "0");
        }

        $this->eventDispatcher->dispatch(new UploadStarted($uuid, $targetFile, $storage['metadata']));

        return $response;
    }

    protected function handlePatch(ServerRequestInterface $request, ResponseInterface $response = null, UuidInterface $uuid = null): ResponseInterface
    {
        if ($this->getHeaderScalar($request, 'Content-Type')  !== 'application/offset+octet-stream') {
            return $this->createResponse(415);
        }

        if (!$uuid) {
            try {
                $uuid = $this->locationProvider->provideUuid($request);
            } catch (UnexpectedValueException $t) {
                return $this->createResponse(404);
            }
        }

        /**
         * @var array $storage
         */
        $storage = $this->storage->get($uuid->getHex());

        if (!$storage) {
            return $this->createResponse(404);
        }

        $defer = $storage['defer'];

        if ($defer) {
            $length = (int) $this->getHeaderScalar($request, 'Upload-Length') ?: 0;
            if ($length) {
                if ($length > $this->maxSize) {
                    return $this->createResponse(413); //Request Entity Too Large
                }
                $storage['length'] = $length;
                $storage['defer'] = $defer =  false;
                $this->storage->set($uuid->getHex(), $storage);
            }
        }

        $targetFile = $this->createFile($storage['file']);

        if (!$this->getFileExists($targetFile)) {
            return $this->createResponse(404);
        }

        $offset = (int) $this->getHeaderScalar($request, 'Upload-Offset') ?: 0;

        $size = $this->getFileSize($targetFile);

        if ($size !== $offset) {
            return $this->createResponse(409, $response); //Conflict
        }

        if ($this->useIntermediateChunk) {
            $chunkFile = new SplFileInfo(tempnam($this->chunkDirectory, $uuid->getHex()));
            $chunkHandle = fopen($chunkFile->getPathname(), 'rb+');
            if (!$chunkHandle) {
                throw new RuntimeException('Can not open file ' . $chunkFile->getPathname());
            }
        }

        $fileHandle = fopen($targetFile->getPathname(), 'rb+');
        if (!$fileHandle) {
            throw new RuntimeException('Can not open file ' . $targetFile->getPathname());
        }

        if (fseek($fileHandle, $offset) !== 0) {
            throw new RuntimeException('Can not set pointer in file');
        }

        try {
            $bytesTransfered = $this->writeInputToFile($request, $this->useIntermediateChunk ? $chunkHandle : $fileHandle, $defer, $offset, $storage['length']);
        } catch (ConflictException $e) {
            return $this->createResponse(409); //Conflict
        }

        if ($this->useIntermediateChunk) {
            $exception = null;
            try {
                if (fseek($chunkHandle, 0) !== 0) {
                    throw new RuntimeException('Can not set pointer in file');
                }

                /**
                 * @todo Test for huge files
                 */
                if (stream_copy_to_stream($chunkHandle, $fileHandle) !== $bytesTransfered) {
                    throw new RuntimeException('Can not copy chunk ' . $chunkFile->getPathname() . ' to target file ' . $targetFile->getPathname());
                }

                fflush($fileHandle);
            } catch (RuntimeException $t) {
                $exception = $t;
            } finally {
                /**
                 * Clean up and rethrow
                 */
                fclose($fileHandle);
                fclose($chunkHandle);
                $this->deleteFile($chunkFile);
                if ($exception) {
                    throw $exception;
                }
            }
        }

        $size = $this->getFileSize($targetFile);

        if ($defer) {
            if ($offset + $bytesTransfered > $this->maxSize) {
                $this->deleteFile($targetFile);
                return $this->createResponse(409, $response);
            }
        } else {
            if ($offset + $bytesTransfered !== $size) {
                $this->deleteFile($targetFile);
                return $this->createResponse(409, $response);
            }
        }

        $response = $this->createResponse(204, $response) //No Content
            ->withHeader('Upload-Offset', (string) $size);

        if ($defer) {
            $response = $response->withHeader('Upload-Defer-Length', "1");
        } elseif ($size === $storage['length']) {
            /**
             * File complete:
             * - Set storage ttl with complete flag, necessary for potential GET-calls
             * - Dispatch UploadComplete Event
             */
            $storage['complete'] = true;
            $this->storage->set($uuid->getHex(), $storage, $this->storageTTLAfterUploadComplete);

            $this->eventDispatcher->dispatch(new UploadComplete($uuid, $targetFile, $storage['metadata']));
        }

        return $response;
    }

    /**
     * Writes file-data from request in chunks to given file handle
     * @param resource $fileHandle
     * @return int Returns number of transfered bytes
     */
    protected function writeInputToFile(ServerRequestInterface $request, $fileHandle, bool $defer, int $offset, int $uploadLength): int
    {
        $bytesTransfered = 0;

        $upload = $request->getBody()->detach();
        if (!$upload) {
            throw new ConflictException("No upload found");
        }

        /**
         * Writing Input to Chunk
         * This in-between step is necessary for checking checksums
         * Reading input in chunks helps to support large files
         */
        try {
            while (!feof($upload)) {
                $chunk = fread($upload, $this->chunkSize);

                if ($chunk === false) {
                    throw new RuntimeException("Read error");
                }

                if ($chunk === "") {
                    /**
                     * Break loop if $chunk is empty
                     */
                    break;
                }

                $bytes = fwrite($fileHandle, $chunk);

                if ($bytes === false) {
                    throw new RuntimeException("Write error");
                }

                if ($bytes !== strlen($chunk)) {
                    throw new RuntimeException("Another write error");
                }

                if (!fflush($fileHandle)) {
                    throw new RuntimeException("Flush error");
                }

                $bytesTransfered += $bytes;

                if ($defer) {
                    if ($offset + $bytesTransfered > $this->maxSize) {
                        /**
                         * @todo Delete file?
                         */
                        throw new ConflictException("Upload exceeds max allowed size");
                    }
                } else {
                    if ($offset + $bytesTransfered > $uploadLength) {
                        /**
                         * @todo Delete file?
                         */
                        throw new ConflictException("Upload exceeds size limit");
                    }
                }
            }
        } finally {
            fclose($upload);
        }

        return $bytesTransfered;
    }

    /**
     * Serves a file, if server settings allow it
     * (not part of tus.io-Protocol)
     */
    protected function handleGet(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->allowGetCalls) {
            return $this->createResponse(405);
        }

        try {
            $uuid = $this->locationProvider->provideUuid($request);
        } catch (UnexpectedValueException $t) {
            return $this->createResponse(400);
        }

        $storage = $this->storage->get($uuid->getHex());

        if (!$storage) {
            return $this->createResponse(404);
        }

        if (!$this->allowGetCallsForPartialUploads && !$storage['complete']) {
            /**
             * File is not uploaded completely
             */
            return $this->createResponse(403);
        }

        $targetFile = $this->createFile($storage['file']);

        if (!$this->getFileExists($targetFile)) {
            return $this->createResponse(404);
        }

        $response =  $this->createResponse(200)
            ->withBody($this->streamFactory->createStreamFromFile($targetFile->getPathname()));

        /**
         * @todo Escape Filename?
         */
        $response = $response->withHeader('Content-Length', (string) $this->getFileSize($targetFile))
            ->withHeader('Content-Disposition', 'attachment; filename="' . $targetFile->getFilename() . '"')
            ->withHeader('Content-Transfer-Encoding', 'binary');

        if (isset($storage['metadata']['type'])) {
            $response = $response->withHeader('Content-Type', $storage['metadata']['type']);
        }

        return $response;
    }
    /**
     * Checks file existance with clearing stat cache
     * @todo Does stat cache affect file_exists?
     */
    protected function getFileExists(SplFileInfo $file): bool
    {
        $pathname = $file->getPathname();
        clearstatcache(false, $pathname);
        return file_exists($pathname);
    }
    /**
     * Get (true) size of file with clearing stat cache
     */
    protected function getFileSize(SplFileInfo $file): int
    {
        $pathname = $file->getPathname();
        clearstatcache(false, $pathname);
        return filesize($pathname) ?: 0;
    }

    /**
     * Create a basic Response
     */
    protected function createResponse(int $code = 200, ResponseInterface $response = null): ResponseInterface
    {
        $response = $response ? $response->withStatus($code) : $this->responseFactory->createResponse($code);
        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Tus-Resumable', '1.0.0');
    }

    /**
     * Get scalar header-value from request
     */
    protected function getHeaderScalar(RequestInterface $request, string $key): ?string
    {
        if ($request->hasHeader($key)) {
            return $request->getHeader($key)[0];
        }
        return null;
    }

    /**
     * Extract metadata-arry from request
     * @see https://tus.io/protocols/resumable-upload.html#upload-metadata
     */
    protected function getMetadata(RequestInterface $request): array
    {
        $metadata = [];
        $metadataHeader = $this->getHeaderScalar($request, 'Upload-Metadata') ?? null;

        if ($metadataHeader) {
            foreach (explode(',', $metadataHeader) as $keyValuePair) {
                $keyValuePair = explode(' ', $keyValuePair);
                if (!isset($keyValuePair[0])) {
                    continue;
                }
                $metadata[$keyValuePair[0]] = isset($keyValuePair[1]) ? base64_decode($keyValuePair[1]) : null;
            }
        }

        return $metadata;
    }

    /**
     * Create file object from path
     */
    protected function createFile(string $pathname): SplFileInfo
    {
        return new SplFileInfo($pathname);
    }

    /**
     * Delete file and log, if it exists and can not be deleted
     */
    protected function deleteFile(SplFileInfo $file): void
    {
        if (!unlink($file->getPathname())) {
            if ($this->getFileExists($file)) {
                $this->logger->notice("Could not delete file " . $file->getPathname());
            }
        }
    }
}
