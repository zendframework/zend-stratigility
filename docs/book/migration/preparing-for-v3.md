# Preparing for version 3

- Since 2.2.0

Version 3 simplifies `MiddlewarePipe` and `Next` dramatically by restricting
them to [PSR-15](https://github.com/php-fig/fig-standards/tree/4b417c91b89fbedaf3283620ce432b6f51c80cc0/proposed/http-handlers)
interface implementations and typehints. However, this also means a number of
backwards compatibility breaks are coming.

To help prepare you for the new version, we have provided a number of features
you can adopt today in order to make your code forwards-compatible.
Additionally, we have marked classes and methods as deprecated where necessary,
and trigger `E_USER_DEPRECATED` errors when using functionality which will no
longer be available.

Below, we list the various changes, and propose ways in which you can update
your code to be forwards-compatible.

## MiddlewarePipe and path segregation

Starting in version 3, `MiddlewarePipe` and `Next` have significantly
different behavior.

First, the signature of `MiddlewarePipe::pipe()` will change to:

```php
public function pipe(
    MiddlewareInterface $middleware
) : void
```

This means the following:

- You can no longer use `pipe()` to perform path segregation.
- You can no longer pipe callable middleware of any type.

To support path segregation, we have introduced
`Zend\Stratigility\Middleware\PathMiddlewareDecorator`. This class accepts two
arguments to its constructor: a string `$pathPrefix` (previously, the `$path`
argument to `MiddlewarePipe::pipe()`), and a middleware implementation. This
class has been backported to version 2.2.0, with usage as follows:

```php
// Previously:
$pipeline->pipe('/api', $apiMiddleware);

// Version 2.2.0+:
$pipeline->pipe(new PathMiddlewareDecorator('/api', $apiMiddleware));
```

Path segregation using this middleware works exactly as it has in previous
versions. (Internally, if you provide both a `$path` and `$middleware` argument,
`MiddlewarePipe::pipe()` creates a `PathMiddlewareDecorator` instance from the
two arguments).

**Decorate middleware you need to segregate by path using
`PathMiddlewareDecorator`.**

To support callable middleware, we have introduced two classes:

- `Zend\Stratigility\Middleware\CallableMiddlewareDecorator` can be used to
  decorate callable middleware following the PSR-15 signature. It replaces the
  class `CallableInteropMiddlewareWrapper`.

- `Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator` can be used to
  decorate callable middleware following the double-pass signature. It replaces
  the class `CallableMiddlewareWrapper`.

Use these classes to decorate your callable middleware when piping them:

```php
// Previously (interop middleware):
$pipeline->pipe(function ($request, $delegate) {
    /* ... */
});

// Version 2.2.0+:
$pipeline->pipe(new CallableMiddlewareDecorator(function ($request, $delegate) {
    /* ... */
}));

// Previously (double-pass middleware):
$pipeline->pipe(function ($request, $response, $next) {
    /* ... */
});

// Version 2.2.0+:
$pipeline->pipe(new DoublePassMiddlewareDecorator(function ($request, $response, $next) {
    /* ... */
}));
```

If you pipe callables directly, you will now trigger an `E_USER_DEPRECATION`
error. Internally, `MiddlewarePipe::pipe()` will decorate them using the classes
noted above.

**Decorate callable middleware before piping using either
`CallableMiddlewareDecorator` or `DoublePassMiddlewareDecorator`.**

## Extending MiddlewarePipe

Starting in version 3, `Zend\Stratigility\MiddlewarePipe` is marked as `final`.
This means you will no longer be able to directly extend it.

We recommend the following:

- If you are extending the class for the sole purpose of piping specific
  middleware, create a PSR-15 `MiddlewareInterface` implementation, and compose
  a `MiddlewarePipe` internally; have your `process()` method proxy to it.
  (You could also optionally implement `RequestHandlerInterface`, which
  `MiddlewarePipe` does in version 3.)

- If you are extending the class in order to provide additional features or
  override methods, create your own PSR-15 `MiddlewareInterface` implementation
  to do so, and copy and paste methods from `MiddlewarePipe` as needed,
  providing the changes you need within your version.

## Deprecated classes

The following classes are now marked as deprecated. Where alternatives are
available, we note them. If no alternative is available, we note why.

### `Zend\Stratigility\MiddlewareInterface`

This interface has been marked as deprecated since 2.0.0, and unused internally
since that release. It is removed with version 3.0.0.

### `Zend\Stratigility\Route`

This is an internal message shared between a `MiddlewarePipe` and a `Next`
instance for purposes of path segregation. In general, it should never be
consumed directly; however, it was never marked as internal or final previously.

If you are extending this class or manipulating instances manually, be aware
that this class is removed in version 3 as it is no longer used internally.

### `Zend\Stratigility\Exception\InvalidMiddlewareException`

This was thrown by `MiddlewarePipe::pipe()`. In version 3, since the sole
argument to that method is type-hinted against the PSR-15 `MiddlewareInterface`,
it is no longer used.

### `Zend\Stratigility\Exception\InvalidRequestTypeException`

This has not been used internally since before version 2.

### `Zend\Stratigility\Delegate\CallableDelegateDecorator`

This was an internal class used by several classes when they were being used
within double-pass systems in order to cast a `callable $next` argument into a
`Interop\Http\ServerMiddleware\DelegateInterface` instance. Since version 3 will
no longer support operation directly within a double-pass architecture, this
class will be removed.

Methods that produce an instance include:
- `MiddlewarePipe::__invoke()`
- `NotFoundHandler::__invoke()`
- `ErrorHandler::__invoke()`

### `Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper`

This class has been deprecated in favor of a new class,
`Zend\Stratigility\Middleware\CallableMiddlewareDecorator`.

### `Zend\Stratigility\Middleware\CallableMiddlewareWrapper`

This class has been deprecated in favor of a new class,
`Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator`.

### `Zend\Stratigility\Middleware\CallableMiddlewareWrapperFactory`

The primary purpose of this class was for composition within a `MiddlewarePipe`
for purposes of decorating callable double-pass middleware. Since
`MiddlewarePipe::pipe()` will no longer accept callables, it will also no longer
need to compose this factory.

### `Zend\Stratigility\Middleware\NoopFinalFactory`

This class has no internal usage, and is removed in version 3.
