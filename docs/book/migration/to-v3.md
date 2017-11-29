# Migrating from version 2 to version 3

- [PHP support](#php-support)
- [PSR-15](#psr-15)
- [Pipeline - `MiddlewarePipe`](#pipeline-middlewarepipe)
- [Removed classes and exceptions](#removed-classes-and-exceptions)
- [Changes in public interface](#changes-in-public-interface)
  - [Signature changes](#signature-changes)
  - [Removed methods](#removed-methods)

## PHP support

We support only PHP 7.1 and above.
PHP 5.6 and 7.0 support has been dropped.

## PSR-15

Since version 3.0.0 Stratigility supports PSR-15 middlewares.
Support of `http-interop/http-middleware` has been dropped.

All middlewares and request handlers now implement PSR-15 interfaces.

## Pipeline - `MiddlewarePipe`

Since version 3.0.0 we allow only piping `MiddlewareInterface` instances
into `MiddlewarePipe` class. All inside wrappers has been removed. If
you'd like to use different middleware style (callable, double-pass)
you have to wrap them and pipe `MiddlewareInterface` instance instead.

> TODO: Are we going to provide some wrappers?

## Removed classes and exceptions

The following classes has been removed:

- `Zend\Stratigility\CallableDelegateDecorator`
- `Zend\Stratigility\CallableInteropMiddlewareWrapper`
- `Zend\Stratigility\CallableMiddlewareWrapper`
- `Zend\Stratigility\CallableMiddlewareWrapperFactory`
- `Zend\Stratigility\MiddlewareInterface` - please use PSR-15 MiddlewareInterface instead
- `Zend\Stratigility\NoopFinalHandler`

The following exceptions has been removed:

- `Zend\Stratigility\Exception\InvalidRequestTypeException`
- `Zend\Stratigility\Exception\MissingResponsePrototypeException`

## Changes in public interface

### Signature changes

- `Next::__construct()` - the second parameter has typehint on
  PSR-15 `RequestHandlerInterface`.

> TODO: below link to PSR-15 is not working as it is not yet accepted.

A number of signatures have been changed due to updating Stratigility to
support [PSR-15](http://www.php-fig.org/psr/psr-15/) instead of
[http-interop/http-server-middleware](https://github.com/http-interop/http-server-middleware)
and [http-interop/http-middleware](https://github.com/http-interop/http-middleware)
(which were the basis for PSR-15). Essentially, these were a matter of
updating typehints on `Interop\Http\ServerMiddleware\DelegateInterface` and
`Interop\Http\Server\RequestHandlerInterface` to PSR-15
`RequestHandlerInterface` and adding return type
`Psr\Http\Message\ResponseInterface`. Signatures affected include:

- `Zend\Stratigility\MiddlewarePipe::process()`
- `Zend\Stratigility\Middleware\ErrorHandler::process()`
- `Zend\Stratigility\Middleware\NotFoundHandler::process()`
- `Zend\Stratigility\Middleware\OriginalMessages::process()`
- `Zend\Stratigility\MiddlewarePipe::process()`

All of these classes implements now PSR-15 `MiddlewareInterface`.

### Removed method

- `MiddlewarePipe::__invoke()` - class is no longer invokable.
  Method `process` should be used instead.

- `MiddlewarePipe::setCallableMiddlewareDecorator()` - as we accept only
  PSR-15 middlewares in pipeline this method is no longer needed.
  Other middlewares types should be wrapped before piping.

- `MiddlewarePipe::setResponsePrototype()` - method no longer needed,
  because we do not support double-pass middlewares anymore.

- `MiddlewarePipe::hasResponsePrototype()` - method no longer needed,
  because we do not support double-pass middlewares anymore.

- `MiddlewarePipe::raiseThrowables()` - method deprecated since 2.0.0.

- `Middleware\ErrorHandler::__invoke()` - class is no longer invokable.
  Method `process` should be used instead.

- `Next::__invoke()` - class is no longer invokable. Method `handle`
  should be used instead.

- `Next::next()` - it was proxy to `handle` method.

- `Next::process()` - please use `handle` method instead.

- `Next::raiseThrowables()` - method deprecated since 2.0.0.
