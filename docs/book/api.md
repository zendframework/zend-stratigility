# API Reference

The following make up the primary API of Stratigility.

> ### http-server-middleware
>
> - Affects: version 3.0.0alpha1
>
> Starting with version 3.0.0, support for http-interop/http-middleware has been
> replaced with support for http-interop/http-server-middleware.

## Middleware

`Zend\Stratigility\MiddlewarePipe` is the primary application interface, and
has been discussed previously. Its API is:

```php
namespace Zend\Stratigility;

use Interop\Http\Server\MiddlewareInterface as ServerMiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface as DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MiddlewarePipe implements ServerMiddlewareInterface
{
    public function pipe(
        string|ServerMiddlewareInterface $path,
        ServerMiddlewareInterface $middleware = null
    );

    public function process(
        ServerRequestInterface $request,
        DelegateInterface $delegate
    ) : ResponseInterface;
}
```

`pipe()` takes up to two arguments. If only one argument is provided,
`$middleware` will be assigned that value, and `$path` will be re-assigned to
the value `/`; this is an indication that the `$middleware` should be invoked
for any path. If `$path` is provided, the `$middleware` will only be executed
for that path and any subpaths.

> ### Request path changes when path matched
>
> When you pipe middleware using a path (other than '' or '/'), the middleware
> is dispatched with a request that strips the matched segment(s) from the start
> of the path.
>
> If, for example, you executed `$pipeline->pipe('/api', $api)`, and this was
> matched via a URI with the path `/api/users/foo`, the `$api` middleware will
> receive a request with the path `/users/foo`. This allows middleware
> segregated by path to be re-used without changes to its own internal routing.

Middleware is executed in the order in which it is piped to the
`MiddlewarePipe` instance.

The `MiddlewarePipe` is itself middleware, and can be executed in stacks that
expect http-interop middleware signatures.

Middleware should either return a response, or the result of
`RequestHandlerInterface::handle()` (which should eventually evaluate to a
response instance).

Within Stratigility, `Zend\Stratigility\Next` provides an implementation
of `RequestHandlerInterface`.

Internally, during execution of the `process()` method, `MiddlewarePipe` creates
an instance of `Zend\Stratigility\Next` (feeding it its queue), executes it, and
returns its response.

## Next

`Zend\Stratigility\Next` is primarily an implementation detail of middleware,
and exists to allow delegating to middleware registered later in the stack. It
is implemented as an http-interop/http-middleware `RequestHandlerInterface`.

Since your middleware needs to return a response, it must:

- Compose a response prototype in the middleware to use to build a response, or a
  canned response to return, OR
- Create and return a concrete response type, OR
- Operate on a response returned by invoking the delegate.

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

    // Provide a new request instance to the delegate:
    return $handler->handle(
        $request->withBodyParams($bodyParams)
    );
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
delegate.

```php
return $handler->handle($request);
```

**Middleware should always return a response, and, if it cannot, return the
result of delegation.**

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

#### ErrorHandler and NotFoundHandler

These two middleware allow you to provide handle PHP errors and exceptions, and
404 conditions, respectively. You may read more about them in the
[error handling chapter](error-handlers.md).

### OriginalMessages

This callable middleware can be used as the outermost layer of middleware in
order to set the original request and URI instances as request attributes for
inner layers.
