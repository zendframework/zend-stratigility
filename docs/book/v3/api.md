# API Reference

The following make up the primary API of Stratigility.

> ### http-server-middleware
>
> - Affects: version 3.0.0alpha1
>
> Starting with version 3.0.0, support for http-interop/http-middleware has been
> replaced with support for psr/http-server-middleware.

## Middleware

`Zend\Stratigility\MiddlewarePipe` is the primary application interface, and
has been discussed previously. Its API is:

```php
namespace Zend\Stratigility;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewarePipe implements MiddlewareInterface, RequestHandlerInterface
{
    public function pipe(MiddlewareInterface $middleware);

    public function handle(ServerRequestInterface $request) : ResponseInterface;

    public function process(
        ServerRequestInterface $request,
        DelegateInterface $delegate
    ) : ResponseInterface;
}
```

Middleware is executed in the order in which it is piped to the
`MiddlewarePipe` instance.

The `MiddlewarePipe` is itself middleware, and can be executed in stacks that
expect PSR-15 middleware signatures. It is also a request handler,
allowing you to use it in paradigms where a request handler is required; when
executed in this way, it will process itself in order to generate a response.

Middleware should either return a response, or the result of
`RequestHandlerInterface::handle()` (which should eventually evaluate to a
response instance).

Internally, `MiddlewarePipe` creates an instance of `Zend\Stratigility\Next` to
use as a `RequestHandlerInterface` implementation to pass to each middleware;
`Next` receives the queue of middleware from the `MiddlewarePipe` instance and
processes each one, calling them with the current request and itself, advancing
its internal pointer until all middleware are executed, or a response is
returned.

## Next

`Zend\Stratigility\Next` is primarily an implementation detail, and exists to
allow delegating to middleware aggregated in the `MiddlewarePipe`. It is
implemented as an PSR-15 `RequestHandlerInterface`.

Since your middleware needs to return a response, the instance receives the
`$handler` argument passed to `MiddlewarePipe::process()` as a fallback request
handler; if the last middleware in the queue calls on its handler, `Next` will
execute the fallback request handler to generate a response to return.

### Providing an altered request:

```php
function ($request, RequestHandlerInterface $handler) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);

    // Delegate will receive the new request instance:
    return $handler->handle(
        $request->withBodyParams($bodyParams)
    );
}
```

### Providing an altered request and operating on the returned response:

```php
function ($request, RequestHandlerInterface $handler) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);

    // Provide a new request instance to the handler:
    $response = return $handler->handle(
        $request->withBodyParams($bodyParams)
    );

    // Return a response with an additional header:
    return $response->withHeader('X-Completed', 'true');
}
```

### Returning a response to complete the request

If your middleware does not need to delegate to another layer, it's time to
return a response.

We recommend creating a new response, or providing your middleware with a
response prototype; this will ensure that the response is specific for your
context.

```php
$prototype = new Response();

function ($request, RequestHandlerInterface $handler) use ($prototype)
{
    $response = $prototype->withAddedHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
}
```

### Delegation

If your middleware is not capable of returning a response, or a particular path
in the middleware cannot return a response, return the result of executing the
handler.

```php
return $handler->handle($request);
```

**Middleware should always return a response, and, if it cannot, return the
result of delegating to the request handler.**

### Raising an error condition

If your middleware cannot complete &mdash; perhaps a database error occurred, a
service was unreachable, etc. &mdash; how can you report the error?

Raise an exception!

```php
function ($request, RequestHandlerInterface $handler) use ($service)
{
    $result = $service->fetchSomething();
    if (! $result->isSuccess()) {
        throw new RuntimeException('Error fetching something');
    }

    /* ... otherwise, complete the request ... */
}
```

Use the [ErrorHandler middleware](error-handlers.md#handling-php-errors-and-exceptions)
to handle exceptions thrown by your middleware and report the error condition to
your users.

## Middleware

Stratigility provides several concrete middleware implementations.

### PathMiddlewareDecorator

If you wish to segregate middleware by path prefix and/or conditionally
execute middleware based on a path prefix, decorate your middleware using
`Zend\Stratigility\Middleware\PathMiddlewareDecorator`.

Middleware decorated by `PathMiddlewareDecorator` will only execute if the
request URI matches the path prefix provided during instantiation.

```php
// Only process $middleware if the URI path prefix matches '/foo':
$pipeline->pipe(new PathMiddlewareDecorator('/foo', $middleware));
```

When the path prefix matches, the `PathMiddlewareDecorator` will strip the path
prefix from the request passed to the decorated middleware. For example, if you
executed `$pipeline->pipe('/api', $api)`, and this was matched via a URI with
the path `/api/users/foo`, the `$api` middleware will receive a request with the
path `/users/foo`. This allows middleware segregated by path to be re-used
without changes to its own internal routing.

### CallableMiddlewareDecorator

`Zend\Stratigility\Middleware\CallableMiddlewareDecorator` provides the ability
to decorate PHP callables that have the same signature as or a compatible
signature to PSR-15's `MiddlewareInterface`. This allows for one-off middleware
creation when creating your pipeline:

```php
$pipeline->pipe(new CallableMiddlewareDecorator(function ($req, $handler) {
    // do some work
    $response = $handler->handle($req);
    // do some work
    return $response;
});
```

### DoublePassMiddlewareDecorator

`Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator` provides the
ability to decorate "double-pass", callable middleware (so-called because you
pass the request _and_ response to the delegate) within a class implementing the
PSR-15 `MiddlewareInterface`. This allows you to adapt existing middleware with
the double-pass interface to work with Stratigility.

```php
$pipeline->pipe(new DoublePassMiddlewareDecorator(function ($req, $res, $next) {
    // do some work
    $response = $next($req, $res);
    // do some work
    return $response;
});
```

`$next` is a callable that decorates Stratigility's `Next` instance; it
ignores the response argument.

The constructor takes an optional second argument, a response prototype. This
will be used to pass to the middleware when it is executed. If no instance is
provided, a zend-diactoros response instance is auto-wired. If you want to use
an alternate PSR-7 `ResponseInterface` implementation, pass it when creating the
decorator instance:

```php
$pipeline->pipe(new DoublePassMiddlewareDecorator(
    $doublePassMiddleware,
    $response
));
```

### ErrorHandler and NotFoundHandler

These two middleware allow you to provide handle PHP errors and exceptions, and
404 conditions, respectively. You may read more about them in the
[error handling chapter](error-handlers.md).

### OriginalMessages

This callable middleware can be used as the outermost layer of middleware in
order to set the original request and URI instances as request attributes for
inner layers.

## Utility Functions

Stratigility provides the following utility functions.

### host

```php
function Zend\Stratigility\host(
  string $host,
  Psr\Http\Server\MiddlewareInterface $middleware
) : Zend\Stratigility\Middleware\HostMiddlewareDecorator
```

`host()` provides a convenient way to perform host name segregation when piping your
middleware.

```php
$pipeline->pipe(host('example.com', $middleware));
```

### path

```php
function Zend\Stratigility\path(
    string $pathPrefix,
    Psr\Http\Server\MiddlewareInterface $middleware
) : Zend\Stratigility\Middleware\PathMiddlewareDecorator
```

`path()` provides a convenient way to perform path segregation when piping your
middleware.

```php
$pipeline->pipe(path('/foo', $middleware));
```

### middleware

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

### doublePassMiddleware

```php
function Zend\Stratigility\doublePassMiddleware(
    callable $middleware,
    Psr\Http\Message\ResponseInterface $responsePrototype = null
) : Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator
```

`doublePassMiddleware()` provides a convenient way to decorate middleware that
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
