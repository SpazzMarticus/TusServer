# Tus - Server

A **server** implementation of the [_"tus.io Resumable File Uploads"_](https://tus.io/) protocol using PSR HTTP standards.

## Installation

Use [composer](https://getcomposer.org/) to install:

```bash
composer require spazzmarticus/tus-server
```

Don't forget to install a [ `PSR-7` ](https://packagist.org/providers/psr/http-message-implementation) and a [ `PSR-17` ](https://packagist.org/providers/psr/http-factory-implementation) implementation you want to use.

## Implements

* [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/): `TusServer` class implements `Psr\Http\Server\RequestHandlerInterface` 

## Uses

* [PSR-3: Logger Interface](https://www.php-fig.org/psr/psr-3/): Optionally, pass a `Psr\Log\LoggerInterface` to `TusServer`.

* [PSR-7: HTTP Message Interface](https://www.php-fig.org/psr/psr-7): An instance of `Psr\Http\Message\ServerRequestInterface` must be passed to `TusServer` .
* [PSR-17: HTTP Factories](https://www.php-fig.org/psr/psr-17): `Responses are created by using a` Psr\Http\Message\ResponseFactoryInterface`


* [PSR-16: Simple Cache](https://www.php-fig.org/psr/psr-16): Is used to **store** necessary _server_-metadata (path to the file,  `Upload-Metadata` passed by client, ...) per upload. Instead of using a volatile cache (like apcu) only, you probably should use a chain containing both a fast volatile and a slower non-volatile cache. (Losing the storage mid-upload does not allow resuming uploads.)

* [PSR-12: Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12): Code is written according to PSR-12

## Test

You can test `TusServer` by installing dev-dependencies (`composer install`) and running the provided `server.php`:

```bash
php -S localhost:8000 example/server.php
```

Open your browser, surf to [localhost:8000/](http://localhost:8000/) and use ([Uppy](https://uppy.io/)) to upload.

Uploads are stored at `example/uploads/...`, the filesystem cache at `example/cache/`. Surf to [localhost:8000/reset](http://localhost:8000/reset) to **permanently delete** both *uploads, intermediate chunks and the metadata-storage*. There may be a server log at `example/log/php-error.php` and a server log at `example/log/tus-server.log` containing some additional information.

## Examples

- [Slim v4](https://github.com/SpazzMarticus/TusServer-Example-Slim)