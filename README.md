# Tus - Server

A **server** implementation of the [_"tus.io Resumable File Uploads"_](https://tus.io/) protocol using PSR HTTP standards. 

## Installation

Use [composer](https://getcomposer.org/) to install:

``` bash
composer require spazzmarticus/tus-server
```

Don't forget to install a [PSR-7](https://packagist.org/providers/psr/http-message-implementation) and [PSR-17](https://packagist.org/providers/psr/http-factory-implementation) implementation you want to use. 

## [PSR](https://www.php-fig.org/)

### Implements

* [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/) - `TusServer` implements `Psr\Http\Server\RequestHandlerInterface` 

### Uses

* [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/) - _(optional)_ Pass a `Psr\Log\LoggerInterface` to `TusServer` 

* [PSR-7: HTTP Message Interface](https://www.php-fig.org/psr/psr-7) - An instance of `Psr\Http\Message\ServerRequestInterface` must be passed to `TusServer` . 
* [PSR-17: HTTP Factories](https://www.php-fig.org/psr/psr-17) - Responses are created by using a ` Psr\Http\Message\ResponseFactoryInterface` 

* [PSR-16: Simple Cache](https://www.php-fig.org/psr/psr-16) - Is used to **store** metadata (path to the file, `Upload-Metadata` passed by client, ... ) while uploading. [see ["Cache as storage?!"](#cache-as-storage) below]

* [PSR-12: Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12) - Code is written and formatted according to PSR-12.

## Demo

You can demo `TusServer` by installing the dev-dependencies ( `composer install` ) and running the provided `server.php` :

``` bash
php -S localhost:8000 example/server.php
```

Open your browser, surf to [localhost:8000/](http://localhost:8000/) and use ([Uppy](https://uppy.io/)) to upload. 

Uploads are stored at `example/uploads/...` , the filesystem cache is at `example/cache/` .

 Surf to [localhost:8000/reset](http://localhost:8000/reset) to **permanently delete** *uploads*, *intermediate chunks* and the *metadata-storage*. There might be an error log at `example/log/php-error.php` and a server log at `example/log/tus-server.log` containing some additional information. 

## Test

Automated testing is done with:

* [PHPUnit](https://github.com/sebastianbergmann/phpunit) - unit- and integration-tests
* [newman](https://github.com/postmanlabs/newman) - Verify `TusServer` meets the tus. io-protocol. 

## Examples

* [Slim v4](https://github.com/SpazzMarticus/TusServer-Example-Slim) - Slim routes allow to directly call this tus-server implementation. 

> 👋 This is how I use tus-server. 

## Cache as storage?!

`TusServer` needs something fast to store metadata about uploads. Since the payload is small and performance is important, caches can be used. 

Instead of using a volatile cache only, you should use a chain containing both a fast volatile **and** a slower non-volatile cache. (Losing the metadata mid-upload does not allow for resuming uploads. )

> 👋 I use [symfony/cache](https://github.com/symfony/cache): 
>
> ``` php
>  $volatileCache = new Symfony\Component\Cache\Adapter\ApcuAdapter('...');
>  $nonVolatileCache = new Symfony\Component\Cache\Adapter\FilesystemAdapter('', 0, __DIR__ . '/...');
>
>  $cacheChain = new Symfony\Component\Cache\Adapter\ChainAdapter([$volatileCache, $nonVolatileCache]);
>
>  $storage = new Symfony\Component\Cache\Psr16Cache($cacheChain);
> ```

## Alternatives

* [ankitpokhrel/tus-php](https://github.com/ankitpokhrel/tus-php) - Did not provide enough flexibility for my needs and is the reason I decided to start my own implementation. (Provides a php tus-client, if you are looking for that.)
