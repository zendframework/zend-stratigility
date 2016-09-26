# Migrating to version 2

Version 2 of Stratigility will be making several breaking changes to the API in
order to provide more flexibility, promote interoperability, and reduce
complexity.

To help you prepare your code for version 2, version 1.3.0 provides several
forwards compatibility features to assist you in the process. However, some
changes will still require changes to your code following the 2.0 release.

## Error handling

Prior to version 1.3, the recommended way to handle errors was via
[error middleware](../error-handlers.md), special middleware that accepts
an additional initial argument representing an error. On top of this, we provide
the concept of a "final handler", pseudo-middleware that is executed by the
`Next` implementation when the middleware stack is exhausted, but no response
has been returned.

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
existing functionality.

### Error handling middleware

We broke the existing `FinalHandler` into two separate pieces, one for handling
404 cases (i.e., no middleware was able to handle the request), and one for
handling errors.

`Zend\Stratigility\Middleware\NotFoundHandler` is a middleware implementation to
register as the *innermost layer* of your application; when invoked, it
immediately returns a 404 response.

In order to work, it needs a prototype response instance.

```php
// setup layers
$app->pipe(/* ... */);
$app->pipe(/* ... */);
$app->pipe(new NotFoundHandler(new Response());

// execute application
```

`Zend\Stratigility\Middleware\ErrorHandler` is a middleware implementation to
register as the *outermost layer* of your application (or one of the outermost
layers). It does the following:

- Creates a PHP error handler that catches any errors in the `error_handling()`
  mask and throws them as `ErrorException` instances.
- Wraps the call to `$next()` in a try/catch block:
  - if no exception is caught, and the result is a response, it returns it.
  - if no exception is caught, it raises an exception, which will be caught.
  - any caught exception is transformed into an error response.

The error response will have a 5XX series status code, and the message will be
derived from the reason phrase, if any is present. You may pass a boolean flag
to the constructor indicating the application is in development mode; if so, the
response will have the stack trace included in the body.

In order to work, it needs a prototype response instance, and, optionally, a
flag indicating the status of development mode (default is production mode):

```php
// setup error handling
$app->pipe(new ErrorHandler(new Response(), $isDevelopmentMode);

// setup layers
$app->pipe(/* ... */);
$app->pipe(/* ... */);
```

As a full example, you can combine the two middleware into the same application
as separate layers:

```php
// setup error handling
$app->pipe(new ErrorHandler(new Response(), $isDevelopmentMode);

// setup layers
$app->pipe(/* ... */);
$app->pipe(/* ... */);

// setup 404 handling
$app->pipe(new NotFoundHandler(new Response());

// execute application
```

### No-op final handler

When using the above strategy, the `FinalHandler` implementation loses most of
its meaning, as you are now handling errors and 404 conditions as middleware
layers.

However, you still need to ensure that the pipeline returns a response,
regardless of how the pipeline is setup, and for that we still need some form of
"final" handler that can do so. (In fact, starting in version 2, the `$out`
argument will be renamed to either `$next` or `$delegate`, and will be a
required argument of the `MiddlewareInterface` and, thus, the `MiddlewarePipe`.)

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
