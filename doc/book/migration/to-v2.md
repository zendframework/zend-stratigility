# Migrating to version 2

Version 2 of Stratigility will be making several breaking changes to the API in
order to provide more flexibility, promote interoperability, and reduce
complexity.

To help you prepare your code for version 2, version 1.3.0 provides several
forwards compatibility features to assist you in the process. However, some
changes will still require changes to your code following the 2.0 release.

## Error handling

Prior to version 1.3, the recommended way to handle errors was via
[error middleware](../error-handlers.md#legacy-error-middleware), special
middleware that accepts an additional initial argument representing an error. On
top of this, we provide the concept of a "final handler", pseudo-middleware that
is executed by the `Next` implementation when the middleware stack is exhausted,
but no response has been returned.

These approaches, however, have several shortcomings:

- No other middleware frameworks implement the error middleware feature, which
  means any middleware that calls `$next()` with the error argument will not
  work in those other systems, and error middleware written for Stratigility
  cannot be composed in other systems.
- The `FinalHandler` implementation hits edge cases when empty responses are
  intended.
- Neither combination works well with error or exception handlers.

Starting in 1.3, we are promoting using standard middleware layers as error
handlers, instead of using the existing error middleware/final handler system.

To achieve this, we have provided some new functionality, as well as augmented
existing functionality:

- [NotFoundHandler middleware](../error-handlers.md#handling-404-conditions)
- [ErrorHandler middleware](../error-handlers.md#handling-php-errors-and-exceptions)
- `Zend\Stratigility\NoopFinalHandler` (see next section)

Updating your application to use these features will ensure you are forwards
compatible with version 2 releases.

### No-op final handler

When using the `NotFoundHandler` and `ErrorHandler` middleware (or custom
middleware you drop in place of them), the `FinalHandler` implementation loses
most of its meaning, as you are now handling errors and 404 conditions as
middleware layers.

However, you still need to ensure that the pipeline returns a response,
regardless of how the pipeline is setup, and for that we still need some form of
"final" handler that can do so. (In fact, starting in version 2, the `$out`
argument is renamed to `$next`, and is a *required* argument of the
`MiddlewareInterface` and, thus, the `MiddlewarePipe`.)

Starting in version 1.3, we now offer a `Zend\Stratigility\NoopFinalHandler`
implementation, which simply returns the response passed to it. You can compose
it in your application in one of two ways:

- By passing it explicitly when invoking the middleware pipeline.
- By passing it to `Zend\Diactoros\Server::listen()`.

If you are not using `Zend\Diactoros\Server` to execute your application, but
instead invoking your pipeline manually, use the following:

```php
$response = $app($request, $response, new NoopFinalHandler());
```

If you are using `Zend\Diactoros\Server`, you will need to pass the final
handler you wish to use as an argument to the `listen()` method; that method
will then pass that value as the third argument to `MiddlewarePipe` as shown
above:

```php
$server->listen(new NoopFinalHandler());
```

Both approaches above are fully forwards compatible with version 2, and will
work in all version 1 releases as well.

(You can also compose your own custom final handler; it only needs to accept a
request and a response, and be guaranteed to return a response instance.)

## Deprecated functionality

The following classes, methods, and arguments are deprecated starting in version
1.3.0, and will be removed in version 2.0.0.

- `Zend\Stratigility\FinalHandler` (class)
- `Zend\Stratigility\Dispatch` (class); this class is marked internal already,
  but anybody extending `Next` and/or this class should be aware of its removal.
- `Zend\Stratigility\ErrorMiddlewareInterface` (interface); error middleware
  should now be implemented per the [error handling section above](#error-handling).
- The `$err` argument to `Zend\Stratigility\Next`'s `__invoke()` method.
  Starting in 1.3.0, if a non-null value is encountered, this method will now
  emit an `E_USER_DEPRECATED` notice, referencing this documentation.

## Interface/signature changes

The following signature changes were made with the 2.0.0 release:

- `Zend\Stratigility\MiddlewareInterface`:
    - The `$out` argument was renamed to `$next`.
    - The `$next` argument is no longer optional/nullable.
    - Each of the implementations shipped with Stratigility were updated, including:
        - `Zend\Stratigility\MiddlewarePipe`
        - `Zend\Stratigility\Middleware\ErrorHandler`
        - `Zend\Stratigility\Middleware\NotFoundHandler`
- `Zend\Stratigility\Next`:
  - The `$done` constructor argument was removed.
  - The (optional) `$err` argument to `__invoke()` was removed.

## Removed functionality

The following classes, methods, and arguments are removed starting in version
2.0.0.

- `Zend\Stratigility\Dispatch` (class)
- `Zend\Stratigility\ErrorMiddlewareInterface` (class)
- `Zend\Stratigility\FinalHandler` (class)
- `Zend\Stratigility\Utils::getArity()` (static method); no longer used
  internally.
- The `$err` argument to `Zend\Stratigility\Next`'s `__invoke()` method. If
  passed, it will now be ignored.
