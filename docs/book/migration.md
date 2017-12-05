# Migrating from version 2 to version 3

In this document, we outline the backwards breaking changes with version 3.0,
and provide guidance on how to upgrade your application to be compatible.

- [PHP support](#php-support)
- [PSR-15](#psr-15)
- [Pipeline (`MiddlewarePipe`)](#pipeline-middlewarepipe)
- [Changes in public interfaces](#changes-in-public-interfaces)
  - [Class additions](#class-additions)
  - [Removed classes and exceptions](#removed-classes-and-exceptions)
  - [Signature changes](#signature-changes)
  - [Removed methods](#removed-methods)

## PHP support

We now support only PHP versions 7.1 and above.  PHP 5.6 and 7.0 support has
been dropped.

## PSR-15

Stratigility now supports only PSR-15 interfaces. Support of
`http-interop/http-middleware` has been dropped.

All middleware and request handlers must now implement PSR-15 interfaces.

## Pipeline - `MiddlewarePipe`

We now only allow piping `Interop\Http\Server\MiddlewareInterface` instances
into the `MiddlewarePipe` class.

In version 2, we had a number of internal utilities for identifying other types
of middleware (callable, double-pass, etc.), and would decorate those within the
`pipe()` method. This is no longer allowed.

If you wish to use those types, you will need to decorate them in a
`MiddlewareInterface` implementation when piping them to the pipeline.

> TODO: Are we going to provide some wrappers?
## Changes in public interfaces

### Class additions

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

The following exceptions have been removed:

- `Zend\Stratigility\Exception\InvalidRequestTypeException`

### Signature changes

- `Next::__construct()`: the second parameter now typehints against the
  PSR-15 `RequestHandlerInterface`.

> TODO: below link to PSR-15 is not working as it is not yet accepted.

A number of signatures have been changed due to updating Stratigility to
support [PSR-15](http://www.php-fig.org/psr/psr-15/) instead of
[http-interop/http-server-middleware](https://github.com/http-interop/http-server-middleware)
and [http-interop/http-middleware](https://github.com/http-interop/http-middleware)
(which were draft specification implementations of PSR-15). Primarily, these
were a matter of updating typehints on
`Interop\Http\ServerMiddleware\DelegateInterface` and
`Interop\Http\Server\RequestHandlerInterface` to the PSR-15
`RequestHandlerInterface`, and adding the return type hint
`Psr\Http\Message\ResponseInterface`.

Signatures affected include:

- `Zend\Stratigility\MiddlewarePipe::process()`
- `Zend\Stratigility\Middleware\ErrorHandler::process()`
- `Zend\Stratigility\Middleware\NotFoundHandler::process()`
- `Zend\Stratigility\Middleware\OriginalMessages::process()`
- `Zend\Stratigility\MiddlewarePipe::process()`

All of these classes now implement the PSR-15 `MiddlewareInterface`.

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

- `Next::__invoke()`: this class is no longer invokable. Use the method `handle`
  instead.

- `Next::next()`: this method was a proxy to the `handle()` method, and no
  longer of use, particularly as the class is an internal detail.

- `Next::process()`: this method was a proxy to the `handle()` method, and no
  longer of use, particularly as the class is an internal detail.

- `Next::raiseThrowables()`: this method has been deprecated since 2.0.0, and
  slated for removal with this version.
