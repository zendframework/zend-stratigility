# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release..

## 0.6.1 - 2014-10-13

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- `composer.json` now specifies `~0.5.0@dev` for the `phly/http` dependency.

## 0.6.0 - 2014-10-13

Updated to psr/http-message 0.3.0 and phly/http 0.5.0. The changes required to do so are not backwards incompatible. In particular, all typehints against `Psr\Http\Message\RequestInterface` have been changed to `Psr\Http\Message\IncomingRequestInterface`, as the middleware in Conduit is expected to be server-side, and accept incoming requests.

### Added

- `Phly\Conduit\Http\Request` now implements `Psr\Http\Message\IncomingRequestInterface`, and defines the following new methods:
  - `getCookieParams()`
  - `setCookieParams($cookies)`
  - `getQueryParams()`
  - `getFileParams()`
  - `getBodyParams()`
  - `setBodyParams($values)`
  - `getPathParams()`
  - `setPathParams(array $values)`
- `Phly\Conduit\Http\Request` adds a `setProtocolVersion()` method, as it is now defined in `Psr\Http\Message\MessageInterface`.
- `Phly\Conduit\Http\Response` adds a `setProtocolVersion()` method, as it is now defined in `Psr\Http\Message\MessageInterface`.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- `Phly\Http\Middleware::__invoke` now typehints the `$request` argument against `Psr\Http\Message\IncomingRequestInterface`.

## 0.5.1 - 2014-10-01

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Updated README:
  - Removes references to `Phly\Http\ResponseInterface` (no longer exists)
  - Updates the version for `psr/http-message` to `~0.2.0@dev`
  - Adds descriptions for `Phly\Conduit\Http\Request` and `Phly\Conduit\Http\Response`.

## 0.5.0 - 2014-10-01

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Updated to psr/http-message 0.2.0 and phly/http 0.4.0:
  - StreamInterface becomes StreamableInterface
  - Stream interface changes:
    - adds attach() and getMetadata() methods
    - removes the $maxLength argument from the getContents() method

## 0.4.5 - 2014-09-17

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Updated `FinalHandler` to report the exception message as part of the response payload.

## 0.4.4 - 2014-09-01

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#6](https://github.com/phly/conduit/pull/6) casts arrays assigned to request property values to `ArrayObject` to fix dereferencing issues.

## 0.4.3 - 2014-08-30

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Improved test coverage.
- Fixed `Phly\Conduit\Http\Request::getBody()` implementation; ensures it proxies to correct method.

## 0.4.2 - 2014-08-30

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Ensures `$originalUrl` is set from the composed request's URL at instantiation of the request decorator; this ensures the property is set from the outset.

## 0.4.1 - 2014-08-30

### Added

- Adds `$originalUrl` to the request implementation; set first time `setUrl()` is called.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Adds `$originalUrl` to the request implementation; set first time `setUrl()` is called. (This was present in the original phly/http implementation, but is removed as of 0.2.0 of that project.)

## 0.4.0 - 2014-08-30

This release adds HTTP decorators for the request and response objects in order to ensure expected functionality is present regardless of the PSR implementation. This ensures greater compatibility with other implementations, while keeping the current implementation robust. It also fortunately poses no backwards compatibility issues.

### Added

- `Phly\Conduit\Http\Request`, a decorator for `Psr\Http\Message\RequestInterface`, which adds the ability to set and retrieve arbitrary object properties.
- `Phly\Conduit\Http\ResponseInterface`, which defines:
  - `write($data)` to proxy to the underlying stream's `write()` method.
  - `end($data = null)` to optionally write to the underlying stream, and then mark the response as complete.
  - `isComplete()` to indicate whether or not the response is complete.
- `Phly\Conduit\Http\Response`, a decorator for `Psr\Http\Message\ResponseInterface` which also implements `Phly\Conduit\Http\ResponseInterface`, and ensure that if the response is complete, it is immutable.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.3.0 - 2014-08-25

This release separates the HTTP functionality into its own package, [phly/http](https://github.com/phly/http). As such, the subnamespaces `Phly\Conduit\Http` and `PhlyTest\Conduit\Http` were removed, as they became part of that package. Additionally, the following changes were made:

- `Middleware::handle()` was renamed to `Middleware::__invoke()`, to be compatible with the `phly/http` server implementation.
- All signatures that referred to the former Http subnamespace now refer to the `phly/http` namespace (`Phly\Http`).
- Examples were rewritten to show instantiating a `Phly\Http\Server` instead of a `Phly\Conduit\Http\Server`.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- `Phly\Conduit\Http\*` were removed; this includes:

  - `AbstractMessage`
  - `Request`
  - `RequestFactory`
  - `Response`
  - `ResponseInterface`
  - `Stream`
  - `Uri`
  - `Server`
  
  Each of these are now part of the [phly/http](https://github.com/phly/http) package; install that package to use them.

### Fixed

- Nothing.

## 0.2.0 - 2014-08-21

Most importantly, this release changes the signature of `Phly\Conduit\Http\Server::createServer()`. Previously, the signature was:

```php
public static function createServer(
  Phly\Conduit\Middleware $middleware,
  Psr\Http\Message\RequestInterface $request = null,
  Phly\Conduit\Http\ResponseInterface $response = null
);
```

It is now:

```php
public static function createServer(
  Phly\Conduit\Middleware $middleware,
  array $server // usually $_SERVER
);
```

A new method, `createServerFromRequest()`, has the original arguments, albeit with the request argument required:

```php
public static function createServer(
  Phly\Conduit\Middleware $middleware,
  Psr\Http\Message\RequestInterface $request,
  Phly\Conduit\Http\ResponseInterface $response = null
);
```

This method will create a response for you if none is provided.

Finally, the constructor is now public, allowing you to instantiate directly if you have each of the middleware, request, and response objects prepared:

```php
public function __construct(
  Phly\Conduit\Middleware $middleware,
  Psr\Http\Message\RequestInterface $request,
  Phly\Conduit\Http\ResponseInterface $response
);
```

### Added

- `Phly\Conduit\Http\RequestFactory`, a static class for populating a `Psr\Http\Message\RequestInterface` instance based on `$_SERVER`. The primary entry method is `fromServer()`:

  ```php
  // Create a new request, based on $_SERVER:
  $request = Phly\Conduit\Http\RequestFactory::fromServer($_SERVER);

  // Populate an existing request, based on $_SERVER:
  $request = Phly\Conduit\Http\RequestFactory::fromServer($_SERVER, $request);
  ```

- `Phly\Conduit\Http\Server::__construct()`; see above.

- `Phly\Conduit\Http\Server::createServerFromRequest()`; see above.

### Deprecated

- Nothing.

### Removed

- `Phly\Conduit\Http\Server` removes all methods for marshaling a request object, and instead delegates to `Phly\Conduit\Http\RequestFactory::fromServer()` when the `createServer()` method is invoked.
- `Phly\Conduit\Next` no longer keeps track of a "slash added" status, as the `Phly\Conduit\Http\Uri` implementation obviates it.

### Fixed

- Used [scrutinizer](https://scrutinizer-ci.com) to refactor almost the entire code base to make it less complex, more stable, and easier to maintain. In many cases, extract method refactors were applied, in ways that keep the public API unchanged, but which remove complexity internally.
- `Phly\Conduit\Http\Server` now keeps track of the initial buffer level, and does not rewind beyond it when invoking `send()`.
- `Phly\Conduit\Http\Request::setUrl()` now throws an exception if neither a string or a `Phly\Conduit\Http\Uri` instance is provided.
- `Phly\Conduit\Http\Stream` now throws exceptions at instantiation if the provided stream is not a resource or a string capable of being a resource.
- `Phly\Conduit\Http\Stream` now detaches the resource when `close()` is called.
- `Phly\Conduit\Http\Stream` now returns false if the stream has been detached when calling `isSeekable()`.
- `Phly\Conduit\Http\Stream` now casts the return value of `fseek()` to the appropriate boolean during `seek()`.

## 0.1.0 - 2014-08-11

Initial release.
