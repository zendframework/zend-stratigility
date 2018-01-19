# Migrating from version 2 to version 3

In this document, we outline the backwards breaking changes with version 3.0,
and provide guidance on how to upgrade your application to be compatible.

- [PHP support](#php-support)
- [PSR-15](#psr-15)
- [Pipeline (`MiddlewarePipe`)](#pipeline-middlewarepipe)
- [Changes in public interfaces](#changes-in-public-interfaces)
  - [Signature changes](#signature-changes)
  - [Class additions](#class-additions)
  - [Removed classes and exceptions](#removed-classes-and-exceptions)
  - [Removed methods](#removed-methods)
  - [Function additions](#function-additions)

## PHP support

We now support only PHP versions 7.1 and above.  PHP 5.6 and 7.0 support has
been dropped.

## PSR-15

Stratigility now supports only PSR-15 interfaces. Support of
`http-interop/http-middleware` has been dropped.

All middleware and request handlers must now implement PSR-15 interfaces,
including those Stratigility implements.

As a result, a number of signatures have been changed.  Primarily, these were a
matter of updating typehints on
`Interop\Http\ServerMiddleware\DelegateInterface` (defined in
[http-interop/http-middleware](https://github.com/http-interop/http-middleware)
0.4 and up, an early draft of PSR-15) and
`Interop\Http\Server\RequestHandlerInterface` (defined in
[http-interop/http-server-handler](https://github.com/http-interop/http-server-handler),
the immediate predecessor to the final spec) to
`Psr\Http\Server\RequestHandlerInterface`, and adding the return type hint
`Psr\Http\Message\ResponseInterface`.

Signatures affected include:

- `Zend\Stratigility\MiddlewarePipe::process()`
- `Zend\Stratigility\Middleware\ErrorHandler::process()`
- `Zend\Stratigility\Middleware\NotFoundHandler::process()`
- `Zend\Stratigility\Middleware\OriginalMessages::process()`
- `Zend\Stratigility\MiddlewarePipe::process()`

All of these classes now implement the PSR-15 `MiddlewareInterface`.

## Pipeline - `MiddlewarePipe`

We now only allow piping `Interop\Http\Server\MiddlewareInterface` instances
into the `MiddlewarePipe` class.

In version 2, we had a number of internal utilities for identifying other types
of middleware (callable, double-pass, etc.), and would decorate those within the
`pipe()` method. This is no longer allowed.

If you wish to use those types, you will need to decorate them using the
appropriate decorators as outlined in the [Class additions](#class-additions)
section.

Additionally, `MiddlewarePipe` is now marked `final`, and may not be directly
extended. Decorate an instance if you wish to provide alternate behavior, or
create your own `MiddlewareInterface` implementation to provide alternate
internal logic.

## Changes in public interfaces

### Signature changes

- `Next::__construct()`: the second parameter now typehints against the
  PSR-15 `RequestHandlerInterface`.

- `Next::handle()`: the method now provides a return typehint of
  `Psr\Http\Message\ResponseInterface`.

- `MiddlewarePipe::pipe()`: reduces the number of arguments to one, which now
  typehints against `Psr\Http\Server\MiddlewareInterface`. This means the method
  can no longer be used to segregate middleware by path. If you want to do that,
  please use `Zend\Stratigility\Middleware\PathMiddlewareDecorator` to decorate
  your middleware and to provide the path prefix it will run under. See the next
  section for details.

- `MiddlewarePipe::process()`: the second parameter now typehints against
  `Psr\Http\Server\RequestHandlerInterface`, and provides a return typehint of
  `Psr\Http\Message\ResponseInterface`.

### Class additions

- `Zend\Stratigility\Middleware\HostMiddlewareDecorator` allows you to segregate
  middleware by a static host name. This allows executing middleware only
  if a particular host matches.

  ```php
  // Segregate to hosts matching 'example.com':
  $pipeline->pipe(new HostMiddlewareDecorator('example.com', $middleware));
  ```

  Alternately, use the `host()` utility function to generate the instance; [see
  below](#host).

- `Zend\Stratigility\Middleware\PathMiddlewareDecorator` allows you to segregate
  middleware by a static URI path prefix. This allows executing middleware only
  if a particular path matches, or segregating a sub-application by path.

  ```php
  // Segregate to paths matching '/foo' as the prefix:
  $pipeline->pipe(new PathMiddlewareDecorator('/foo', $middleware));
  ```

  Alternately, use the `path()` utility function to generate the instance; [see
  below](#path).

- `Zend\Stratigility\Middleware\CallableMiddlewareDecorator` provides the
  functionality that was formerly provided by
  `Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper`: it provides
  the ability to decorate PHP callables that have the same or compatible
  signatures to the PSR-15 `MiddlewareInterface`. This allows for one-off piping
  of middleware:

  ```php
  $pipeline->pipe(new CallableMiddlewareDecorator(function ($req, $handler) {
      // do some work
      $response = $next($req, $handler);
      // do some work
      return $response;
  });
  ```

  The arguments and return value can be type-hinted, but do not need to be. The
  decorator provides some checking on the return value in order to raise an
  exception if a response is not returned.

  Alternately, use the `middleware()` utility function to generate the instance;
  [see below](#middleware).

- `Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator` provides the
  functionality that was formerly provided by `Zend\Stratigility\Middleware\CallableMiddlewareWrapper`.
  The class now makes the response prototype argument to the constructor
  optional, and falls back to a zend-diactoros response instance if that library
  is installed. Internally, it decorates the `$handler` as a callable.

  ```php
  $pipeline->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
      // do some work
      $response = $next($req, $res);
      // do some work
      return $response;
  });
  ```

  Per recommendations in previous versions, if you are using double-pass
  middleware, do not operate on the response passed to the middleware; instead,
  only operate on the response returned by `$next`, or produce a concrete
  response yourself.

  Alternately, use the `doublePassMiddleware()` utility function to create the
  instance; [see below](#doublepassmiddleware).

- `Zend\Stratigility\Exception\ExceptionInterface` - marker for
  package-specific exceptions.

### Removed classes and exceptions

The following classes have been removed:

- `Zend\Stratigility\Delegate\CallableDelegateDecorator`
- `Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper`
- `Zend\Stratigility\Middleware\CallableMiddlewareWrapper`
- `Zend\Stratigility\Middleware\CallableMiddlewareWrapperFactory`
- `Zend\Stratigility\MiddlewareInterface` (Please use the PSR-15
  `MiddlewareInterface` instead.)
- `Zend\Stratigility\NoopFinalHandler`
- `Zend\Stratigility\Route`. This was an internal class used by `MiddlewarePipe`
  and `Next`, and its removal should not affect consumers.

The following exceptions have been removed:

- `Zend\Stratigility\Exception\InvalidMiddlewareException` (this is no longer
  thrown by `MiddlewarePipe`, and thus no longer necessary).
- `Zend\Stratigility\Exception\InvalidRequestTypeException`

### Removed methods

- `MiddlewarePipe::__invoke()`: the class is no longer invokable.
  Use the method `process` instead.

- `MiddlewarePipe::setCallableMiddlewareDecorator()`: since we now accept only
  PSR-15 middleware implementations within `MiddlewarePipe`, this method is no
  longer needed. Other middleware types should be decorated in a
  `MiddlewareInterface` implementation prior to piping.

- `MiddlewarePipe::setResponsePrototype()`: this method is no longer needed,
  due to removing support for non-`MiddlewareInterface` types.

- `MiddlewarePipe::hasResponsePrototype()`: this method is no longer needed,
  due to removing support for non-`MiddlewareInterface` types.

- `MiddlewarePipe::raiseThrowables()`: this method has been deprecated since
  2.0.0, and slated for removal with this version.

- `Middleware\ErrorHandler::__invoke()`: this class is no longer invokable.
  Use the `process` method instead.

- `Middleware\NotFoundHandler::__invoke()`: this class is no longer invokable.
  Use the `process` method instead.

- `Next::__invoke()`: this class is no longer invokable. Use the method `handle`
  instead.

- `Next::next()`: this method was a proxy to the `handle()` method, and no
  longer of use, particularly as the class is an internal detail.

- `Next::process()`: this method was a proxy to the `handle()` method, and no
  longer of use, particularly as the class is an internal detail.

- `Next::raiseThrowables()`: this method has been deprecated since 2.0.0, and
  slated for removal with this version.

### Function additions

Release 3.0 adds the following utility functions:

#### host

```php
function Zend\Stratigility\host(
  string $host,
  Psr\Http\Server\MiddlewareInterface $middleware
) : Zend\Stratigility\Middleware\HostMiddlewareDecorator
```

This is a convenience wrapper around instantiation of a
`Zend\Stratigility\Middleware\HostMiddlewareDecorator` instance:

```php
$pipeline->pipe(host('example.com', $middleware));
```

#### path

```php
function Zend\Stratigility\path(
  string $pathPrefix,
  Psr\Http\Server\MiddlewareInterface $middleware
) : Zend\Stratigility\Middleware\PathMiddlewareDecorator
```

This is a convenience wrapper around instantiation of a
`Zend\Stratigility\Middleware\PathMiddlewareDecorator` instance:

```php
$pipeline->pipe(path('/foo', $middleware));
```

#### middleware

```php
function Zend\Stratigility\middleware(
    callable $middleware
) : Zend\Stratigility\Middleware\CallableMiddlewareDecorator
```

`middleware()` provides a convenient way to decorate callable middleware that
implements the PSR-15 middleware signature when piping it to your application.

```php
$pipeline->pipe(middleware(function ($request, $handler) {
  // ...
});
```

#### doublePassMiddleware

```php
function Zend\Stratigility\doublePassMiddleware(
    callable $middleware,
    Psr\Http\Message\ResponseInterface $responsePrototype = null
) : Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator
```

`doublePassiddleware()` provides a convenient way to decorate middleware that
implements the double pass middleware signature when piping it to your application.

```php
$pipeline->pipe(doublePassMiddleware(function ($request, $response, $next) {
  // ...
});
```

If you are not using zend-diactoros as a PSR-7 implementation, you will need to
pass a response prototype as well:

```php
$pipeline->pipe(doublePassMiddleware(function ($request, $response, $next) {
  // ...
}, $response);
```
