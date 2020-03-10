<?php

use SpazzMarticus\Tus\Events\UploadComplete;
use SpazzMarticus\Tus\TusServer;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SpazzMarticus\Tus\Factories\OriginalFilenameFactory;
use SpazzMarticus\Tus\Providers\ParameterLocationProvider;

ini_set('display_errors', "1");
ini_set('display_startup_errors', "1");
ini_set('html_errors', "0");
ini_set("error_log", __DIR__ . '/php-error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ExampleMiddleware.php';

/**
 * Directories to write uploads an storage to
 */
$uploadDirectory = __DIR__ . '/uploads/';
$chunkDirectory = __DIR__ . '/chunks/';
$cacheDirectory  = __DIR__ . '/storage/';

/**
 * PSR-7 Request
 * @see https://packagist.org/providers/psr/http-message-implementation
 */
$request = ServerRequestFactory::fromGlobals();

/**
 * PSR-17 HTTP Factories
 * - ResponseFactoryInterface
 * - StreamFactoryInterface
 * @see https://packagist.org/providers/psr/http-factory-implementation
 */
$responseFactory = new ResponseFactory();
$streamFactory = new StreamFactory();

/**
 * ExampleMiddleware to serve uploader.html and reset (delete) uploads and cache
 *
 * PSR-15 HTTP Server Request Handlers
 * - Psr\Http\Server\MiddlewareInterface
 * @see https://packagist.org/providers/psr/http-server-handler-implementation
 */
$middleware = new \SpazzMarticus\Example\ExampleMiddleware($responseFactory, $streamFactory, $uploadDirectory, $chunkDirectory, $cacheDirectory);

/**
 * PSR-16 Simple Cache (Common Interface for Caching Libraries)
 * - Psr\SimpleCache\CacheInterface
 * @see https://packagist.org/providers/psr/simple-cache-implementation
 *
 * Use (at least at the end of a cache chain) a non-volatile storage to truly allow resumable uploads
 */
$storage = new FilesystemCachePool(new Filesystem(new Local($cacheDirectory)), '');

/**
 * PSR-14 Event Dispatcher
 * - Psr\EventDispatcher\EventDispatcherInterface
 * @see https://packagist.org/providers/psr/event-dispatcher-implementation
 */
$dispatcher = new EventDispatcher();
/**
 * Add your event handlers:
 * - UploadStarted
 * - UploadComplete
 */
$dispatcher->addListener(UploadComplete::class, function (UploadComplete $event) {
});

/**
 * (optional)
 * PSR-3 Logger Interface
 * - Psr\Log\LoggerInterface
 * @see https://packagist.org/providers/psr/log-implementation
 */
$logger = new Logger('tus-server', [
    new StreamHandler(__DIR__ . '/tus-server.log'),
]);

/**
 * Dependencies from this implementation:
 * - SpazzMarticus\Tus\Factories\FilenameFactoryInterface defines where the upload file should go
 * - SpazzMarticus\Tus\Factories\LocationProviderInterface defines the server endpoint, which should be used
 */

$filenameFactory = new OriginalFilenameFactory($uploadDirectory);
$locationProvider = new ParameterLocationProvider();

/**
 * PSR-15 HTTP Server Request Handlers
 * - RequestHandlerInterface
 */
$tus = new TusServer($responseFactory, $streamFactory, $storage, $dispatcher, $filenameFactory, $locationProvider);

$tus->setLogger($logger); //(optional) Add a logger

$tus->setAllowGetCalls(true, null);
// $tus->setUseIntermediateChunk(true, $chunkDirectory); //Using intermediate chunks is required when using checksums (which currently are not implemented)

$response = $middleware->process($request, $tus);

/**
 * Used to emit PSR-7 Response
 * @see https://stackoverflow.com/a/48717426 - Simple code to emit a PSR-7 Response
 */
$emitter = new SapiEmitter();
$emitter->emit($response);
