# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release..

## 0.11.0 - TBD

This release makes several backwards-incompatible changes.

- `Middleware` was renamed to `MiddlewarePipe`. Otherwise, the behavior is
  exactly the same.
- `Next` was rewritten to have a consistent invocable signature: 
  `function (ServerRequestInterface $request, ResponseInterface $response, $err = null)`
  This change simplifies the logic, removes bugs caused by edge cases, and leads
  to consistent usage that's easier to remember.

### Added

- `Phly\Conduit\MiddlewareInterface`, which provides an interface to typehint
  against for middleware. It's usage is not enforced (only a callable is
  required), but `Phly\Conduit\Dispatch` contains optimizations based on the
  interface.
- `Phly\Conduit\ErrorMiddlewareInterface`, which provides an interface to typehint
  against for error-handling middleware. It's usage is not enforced (only a
  callable with arity 4 is required), but `Phly\Conduit\Dispatch` contains
  optimizations based on the interface.
- `Phly\Conduit\MiddlewarePipe` (replaces by `Phly\Conduit\Middleware`).

### Deprecated

- Nothing.

### Removed

- `Phly\Conduit\Next::__construct` no longer accepts the `$request` or
  `$response` arguments, as the values are no longer stored internally.
- `Phly\Conduit\Middleware` (replaced by `Phly\Conduit\MiddlewarePipe`).

### Fixed

- `MiddlewarePipe` was updated to use an `SplQueue` instance internally for
  modeling the middleware pipeline.

## 0.10.2 - 2015-01-21

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- The logic in `Next` was updated to ensure that if a trailing slash was present
  in the path, but not the route, resetting the request URI path retains it.

## 0.10.1 - 2015-01-20

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- The logic in `Middleware` was changed to store the route as provided, without
  stripping the trailing slash. This allows matching with or without it.
- The logic in `Next` was updated to ensure that if a trailing slash was present
  in the route, resetting the request URI path retains it; alternately, if none
  was present, it is omitted.

## 0.10.0 - 2015-01-19

### Added

- `FinalHandler::__invoke`'s signature was modified to require the error
  argument, as well as a request and response instance. It now also returns a
  response.
- `Next::__invoke`'s signature was modified to remove the typehint from the
  second argument, and to add a third argument, typehinted against
  `Psr\Http\Message\ResponseInterface`. This change allows passing both an
  updated request and response in error conditions. It also now passes all three
  arguments to the final handler.

### Deprecated

- Nothing.

### Removed

- `FinalHandler::__construct` removes the arguments representing the request and
  response instances.

### Fixed

- The changes listed in "Added" and "Removed" fix a condition whereby error
  handlers were not getting updated response instances, causing those that
  introspect the response to fail. With the changes, behavior returns to normal.

## 0.9.1 - 2015-01-19

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Use phly/http >= 0.8.2, as that is the first version that properly supports
  PHP 5.4, allowing Conduit to work under PHP 5.4.
- Updated return value annotation in `Middleware::pipe` to read solely `self`.

## 0.9.0 - 2015-01-18

This version syncs Conduit with psr/http-message 0.6.0 and phly/http 0.8.1. The
primary changes are:

- `Phly\Conduit\Http\Request` now implements
  `Psr\Http\Message\ServerRequestInterface`, and extends
  `Phly\Http\ServerRequest`, which means it is also now immutable. It no longer
  provides property access to attributes, and also now stores the original
  request, not the original URI, as a property, providing an accessor to it.
- `Phly\Conduit\Http\Response` now implements
  `Psr\Http\Message\ResponseInterface`, which means it is now immutable.
- The logic in `Phly\Conduit\Next`'s `__invoke()` was largely rewritten due to
  the fact that the request/response pair are now immutable, and the fact that
  the URI is now an object (simplifying many operations).
- The logic in `Phly\Conduit\Middleware`, `Phly\Conduit\Dispatch`, and
  `Phly\Conduit\FinalHandler` also needed slight updates to work with the
  request/response changes.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.


## 0.8.2 - 2014-11-05

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- `Phly\Conduit\Http\Request::$params`, as it is no longer used.

### Fixed

- `README.md` was updated to reference `OutgoingResponseInterface` instead of `ResponseInterface`.

## 0.8.1 - 2014-11-04

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- `Phly\Conduit\Http\Request` now proxies the property overloading methods to the underlying request's "attributes" methods.

## 0.8.0 - 2014-11-04

Updates to psr/http-message 0.5.1 and phly/http 0.7.0. These libraries had several BC incompatible changes, requiring BC-breaking changes in Conduit.

### Added

- `Phly\Conduit\Http\Request::getAttribute($attribute, $default = null)`
- `Phly\Conduit\Http\Request::setAttribute($attribute, $value)`
- `Phly\Conduit\Http\Response::setStatus($code, $reasonPhrase = null)` (replaces `setStatusCode()` and `setReasonPhrase()`)

### Deprecated

- Nothing.

### Removed

- Removed all setters except for `setUrl()` in `Phly\Conduit\Http\Request`.
- Removed `setStatusCode()` and `setReasonPhrase()` from `Phly\Conduit\Http\Response` (replaced with `setStatus()`).

### Fixed

- `Phly\Conduit\Middleware` now typehints on `Psr\Http\Message\OutgoingResponseInterface` instead of `Psr\Http\Message\ResponseInterface` (which was removed).

## 0.7.0 - 2014-10-18

Updates to psr/http-message 0.4.0 and phly/http 0.6.0. These libraries had several BC incompatible changes, requiring BC-breaking changes in Conduit.

### Added

- Specifying `array` as the only accepted input type and return type for all `IncomingRequestInterface`-specific methods.
- Added `(set|get)Attributes()` to the request decorator (replaces `(set|get)PathParams()`.

### Deprecated

- Nothing.

### Removed

- Removed of `setHeaders()` and `addHeaders()` in both the request and response decorators.
- Removed `(set|get)PathParams()` in the request decorator (replaced by `(set|get)Attributes()`.

### Fixed

- Updated `composer.json` to push `PhlyTest\\Conduit\\` namespace autoloading to the `autoload-dev` section (meaning no entry will be added when generating production autoloader rules).

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
