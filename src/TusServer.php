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
use SpazzMarticus\Tus\Services\FileService;
use SpazzMarticus\Tus\Services\MetadataService;
use SplFileInfo;

/**
 * @todo Check for extensions/MIME? (Extension like TargetFileFactory? Can this be checked with first chunk?)
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
    protected FileService $fileService;
    protected FilenameFactoryInterface $targetFileFactory;
    protected LocationProviderInterface $locationProvider;
    protected MetadataService $metadataService;

    /**
     * Size Settings
     */
    protected int $maxSize = 1_073_741_824;

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
        $this->fileService = new FileService();
        $this->targetFileFactory = $targetFileFactory;
        $this->locationProvider = $locationProvider;
        $this->metadataService = new MetadataService();
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
        $method = $this->getHeaderScalar($request, 'X-HTTP-Method-Override') ?? $request->getMethod();

        $clientVersion = $this->getHeaderScalar($request, 'Tus-Resumable');

        /**
         * Check for supported versions. Get calls - since not part of protocol - do usually not include a client version
         */
        if (!in_array($clientVersion, self::SUPPORTED_VERSIONS) && $method !== 'GET') {
            return $this->createResponse(412); //Precondition Failed
        }


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

        $storage = $this->storage->get($uuid->getHex()->toString());

        if (!$storage) {
            return $this->createResponse(404);
        }

        $targetFile = $this->fileService->instance($storage['file']);

        if (!$this->fileService->exists($targetFile)) {
            $this->storage->delete($uuid->getHex()->toString());
            return $this->createResponse(404);
        }

        $size = $this->fileService->size($targetFile);

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

        $metadata = $this->metadataService->getMetadata($request);

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

        $this->storage->set($uuid->getHex()->toString(), $storage);

        try {
            $this->fileService->create($targetFile);
        } catch (RuntimeException $exception) {
            $this->storage->delete($uuid->getHex()->toString());
            throw $exception;
        }

        //Created
        $response = $this->createResponse(201)
            ->withHeader('Location', (string)$this->locationProvider->provideLocation($uuid, $request));

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
        $storage = $this->storage->get($uuid->getHex()->toString());

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
                $this->storage->set($uuid->getHex()->toString(), $storage);
            }
        }

        $targetFile = $this->fileService->instance($storage['file']);

        if (!$this->fileService->exists($targetFile)) {
            return $this->createResponse(404);
        }

        $offset = (int) $this->getHeaderScalar($request, 'Upload-Offset') ?: 0;

        $size = $this->fileService->size($targetFile);

        if ($size !== $offset) {
            /**
             * Don't delete file, client can continue upload with correct offset
             */
            return $this->createResponse(409, $response); //Conflict
        }

        if ($this->useIntermediateChunk) {
            $chunkFile = new SplFileInfo(tempnam($this->chunkDirectory, $uuid->getHex()->toString()));
            $chunkHandle = $this->fileService->open($chunkFile);
        }

        $fileHandle = $this->fileService->open($targetFile);
        $this->fileService->point($fileHandle, $offset);

        try {
            /**
             * @psalm-suppress PossiblyUndefinedVariable
             * $this->useIntermediateChunk is not altered while running this method.
             */
            $bytesTransfered = $this->fileService->copyFromStream($this->useIntermediateChunk ? $chunkHandle : $fileHandle, $request->getBody(), ($defer ? $this->maxSize : $storage['length']) - $offset);
        } catch (ConflictException $e) {
            /**
             * Delete upload on Conflict, because upload size was exceeded
             */
            $this->tryDeleteFile($targetFile);
            $this->storage->delete($uuid->getHex()->toString());
            return $this->createResponse(409); //Conflict
        }

        if ($this->useIntermediateChunk) {
            unset($chunkHandle);
            $exception = null;
            try {
                /**
                 * @todo Test for huge files, test with apache/nginx, php built in is RAM hungry
                 */
                /**
                 * @psalm-suppress PossiblyUndefinedVariable
                 * $this->useIntermediateChunk is not altered while running this method.
                 */
                if (
                    $this->fileService->copyFromStream(
                        $fileHandle,
                        $this->streamFactory->createStreamFromFile($chunkFile->getPathname()),
                    ) !== $bytesTransfered
                ) {
                    throw new RuntimeException('Error when copying ' . $chunkFile->getPathname() . ' to target file ' . $targetFile->getPathname());
                }
            } catch (RuntimeException $t) {
                $exception = $t;
            } finally {
                /**
                 * Clean up and rethrow
                 */
                unset($fileHandle);
                $this->tryDeleteFile($chunkFile);
                $this->storage->delete($uuid->getHex()->toString());
                if ($exception) {
                    throw $exception;
                }
            }
        }

        $size = $this->fileService->size($targetFile);

        if ($defer) {
            if ($offset + $bytesTransfered > $this->maxSize) {
                $this->tryDeleteFile($targetFile);
                $this->storage->delete($uuid->getHex()->toString());
                return $this->createResponse(409, $response);
            }
        } else {
            if ($offset + $bytesTransfered !== $size) {
                $this->tryDeleteFile($targetFile);
                $this->storage->delete($uuid->getHex()->toString());
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
            $this->storage->set($uuid->getHex()->toString(), $storage, $this->storageTTLAfterUploadComplete);

            $this->eventDispatcher->dispatch(new UploadComplete($uuid, $targetFile, $storage['metadata']));
        }

        return $response;
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

        $storage = $this->storage->get($uuid->getHex()->toString());

        if (!$storage) {
            return $this->createResponse(404);
        }

        if (!$this->allowGetCallsForPartialUploads && !$storage['complete']) {
            /**
             * File is not uploaded completely
             */
            return $this->createResponse(403);
        }

        $targetFile = $this->fileService->instance($storage['file']);

        if (!$this->fileService->exists($targetFile)) {
            return $this->createResponse(404);
        }

        $response =  $this->createResponse(200)
            ->withBody($this->streamFactory->createStreamFromFile($targetFile->getPathname()));

        /**
         * Filename currently not escaped
         * @see https://stackoverflow.com/a/5677844
         */
        $response = $response->withHeader('Content-Length', (string) $this->fileService->size($targetFile))
            ->withHeader('Content-Disposition', 'attachment; filename="' . $targetFile->getFilename() . '"')
            ->withHeader('Content-Transfer-Encoding', 'binary');

        if (isset($storage['metadata']['type'])) {
            $response = $response->withHeader('Content-Type', $storage['metadata']['type']);
        }

        return $response;
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
        return $request->getHeaderLine($key) ?: null;
    }

    protected function tryDeleteFile(SplFileInfo $file): void
    {
        try {
            $this->fileService->delete($file);
        } catch (RuntimeException $exception) {
            $this->logger->notice('Could not delete file ' . $file->getPathname());
        }
    }
}
