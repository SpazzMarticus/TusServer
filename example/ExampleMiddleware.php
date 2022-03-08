<?php

namespace SpazzMarticus\Example;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class ExampleMiddleware implements MiddlewareInterface
{
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;
    protected string $uploadDirectory;
    protected string $chunkDirectory;
    protected string $storageDirectory;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory, string $uploadDirectory, string $chunkDirectory, string $storageDirectory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->uploadDirectory = $uploadDirectory;
        $this->chunkDirectory = $chunkDirectory;
        $this->storageDirectory = $storageDirectory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            if ($request->getUri()->getPath() === '/reset') {
                /**
                 * Reset upload directory and cache
                 */
                $this->deleteDirectories($this->uploadDirectory);
                $this->deleteDirectories($this->chunkDirectory);
                $this->deleteDirectories($this->storageDirectory);
                return $this->responseFactory->createResponse(302)
                    ->withHeader('Location', '/'); //Redirect back to root
            } elseif ($request->getUri()->getPath() === '/' && empty($request->getQueryParams())) {
                /**
                 * Serve uploader
                 */
                return $this->responseFactory->createResponse()
                    ->withBody($this->streamFactory->createStreamFromFile(__DIR__ . '/uploader.html'));
            }
        }

        $this->createDir($this->uploadDirectory);
        $this->createDir($this->chunkDirectory);
        $this->createDir($this->storageDirectory);
        /**
         * Pass request to tus server
         */
        return $handler->handle($request);
    }

    /**
     * https://stackoverflow.com/a/3349792 - Delete directory with files in it?
     * @param string $dir Directory to delete
     */

    private function deleteDirectories(string $dir): void
    {
        if (is_dir($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator(
                $it,
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }
    }

    private function createDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                throw new RuntimeException('Can\'t create directory');
            }
        }
    }
}
