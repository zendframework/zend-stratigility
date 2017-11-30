# API Reference

The following make up the primary API of Stratigility.

## Middleware

`Zend\Stratigility\MiddlewarePipe` is the primary application interface, and
has been discussed previously. Its API is:

```php
namespace Zend\Stratigility;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MiddlewarePipe implements ServerMiddlewareInterface
{
    public function pipe(
        string|callable|ServerMiddlewareInterface $path,
        callable|ServerMiddlewareInterface $middleware = null
    );

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $delegate
    ) : ResponseInterface;

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
expect the `__invoke()` signature (via the `__invoke()` signature), or stacks
expecting http-interop middleware signatures (via the `process()` method).


When using `__invoke()`, the callable `$out` argument should either implement
delegator/request handler interface from `http-interop/http-middleware`
(depends on version you are using), or use the signature:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

function (
    ServerRequestInterface $request,
    ResponseInterface $response
) : ResponseInterface
```

Most often, you can pass an instance of `Zend\Stratigility\NoopFinalHandler` for
`$out` if invoking a middleware pipeline manually; otherwise, a suitable
callback will be provided for you (typically an instance of
`Zend\Stratigility\Next`, which `MiddlewarePipe` creates internally before
dispatching to the various middleware in its pipeline).

Middleware should either return a response, or the result of
`$next()/DelegateInterface::process()/RequestHandlerInterface::handle()`
(which should eventually evaluate to a response instance).

Within Stratigility, `Zend\Stratigility\Next` provides an implementation
compatible with either usage.

`MiddlewarePipe` implements the http-interop/http-middleware server-side
middleware interface, and thus provides a `process()` method. This method
requires a `ServerRequestInterface` instance and an http-interop/http-middleware
`DelegateInterface` instance on invocation; the latter can be a `Next` instance,
as it also implements that interface.

Internally, for both `__invoke()` and `process()`, `MiddlewarePipe` creates an
instance of `Zend\Stratigility\Next` (feeding it its queue), executes it, and
returns its response.

### Response prototype

You may compose a "response prototype" in the `MiddlewarePipe`. When present,
any callable middleware piped to the instance will be wrapped in a decorator
(see the [section on middleware decorators](#middleware-decorators), below) such
that it will now conform to http-interop middleware interfaces.

To use this functionality, inject the prototype before piping middleware:

```php
$pipeline = new MiddlewarePipe();
$pipeline->setResponsePrototype(new Response());
```

## Next

`Zend\Stratigility\Next` is primarily an implementation detail of middleware,
and exists to allow delegating to middleware registered later in the stack. It
is implemented both as a functor and as an http-interop/http-middleware
`DelegateInterface`.

### Functor invocation

Because `Psr\Http\Message`'s interfaces are immutable, if you make changes to
your Request and/or Response instances, you will have new instances, and will
need to make these known to the next middleware in the chain. `Next` expects
these arguments for every invocation.

```php
class Next
{
    public function __invoke(
        Psr\Http\Message\ServerRequestInterface $request,
        Psr\Http\Message\ResponseInterface $response
    ) : Psr\Http\Message\ResponseInterface;
}
```

You should **always** either capture or return the return value of `$next()`
when calling it in your application, or return a response yourself.

> ### $response argument
>
> Using the `$response` argument is unsafe when using delegation, as an inner
> layer could return an entirely different response, ignoring any changes you
> may have introduced previously. Additionally, when manipulating the response
> from an inner layer, you may be inheriting unwanted context.
>
> As such, we recommend ignoring the `$response` argument and doing one of the
> following:
>
> - For innermost middleware that will be returning a response without
>   delegation, we recommend instantiating and returning a concrete
>   response instance. [Diactoros provides a number of convenient custom responses](https://docs.zendframework.com/zend-diactoros/custom-responses/).
> - For middleware delegating to another layer, operate on the *returned*
>   response instead:
>
>   ```php
>   $response = $next($request, $response);
>   return $response->withHeader('X-Foo', 'Bar');
>   ```

### Delegate invocation

When invoked as a `DelegateInterface`, the `process()` method will be invoked, and
passed a `ServerRequestInterface` instance *only*. If you need to return a response,
you will need to:

- Compose a response prototype in the middleware to use to build a response, or a
  canned response to return, OR
- Create and return a concrete response type, OR
- Operate on a response returned by invoking the delegate.

### Providing an altered request:

```php
// Standard invokable:
function ($request, $response, $next) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);
    return $next(
        $request->withBodyParams($bodyParams), // Next will pass the new
        $response                              // request instance
    );
}

// http-interop invokable:
function ($request, DelegateInterface $delegate) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);

    // Delegate will receive the new request instance:
    return $delegate->process(
        $request->withBodyParams($bodyParams)
    );
}
```

### Providing an altered request and operating on the returned response:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $response = $next(
        $request->withBodyParams($bodyParser($request)),
        $response
    );

    return $response->withAddedHeader('Cache-Control', [
}

// http-interop invokable:
function ($request, DelegateInterface $delegate) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);

    // Provide a new request instance to the delegate:
    return $delegate->process(
        $request->withBodyParams($bodyParams)
    );
}
```

### Returning a response to complete the request

If your middleware does not need to delegate to another layer, it's time to
return a response.

While we pass a response when using `Next` as a functor, we recommend creating
a new response, or providing your middleware with a response prototype; this
will ensure that the response is specific for your context.

```php
$prototype = new Response();

// Standard invokable signature:
function ($request, $response, $next) use ($prototype)
{
    $response = $prototype->withAddedHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);

    return $response;
}

// http-interop invokable signature:
function ($request, DelegateInterface $delegate) use ($prototype)
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

If using the legacy middleware signature, invoke the `$next` argument:

```php
return $next($request, $response);
```

If using a `DelegateInterface`, invoke its `process()` method:

```php
return $delegate->process($request);
```

**Middleware should always return a response, and, if it cannot, return the
result of delegation.**


### Raising an error condition

If your middleware cannot complete &mdash; perhaps a database error occurred, a
service was unreachable, etc. &mdash; how can you report the error?

Raise an exception!

```php
function ($request, $response, $next) use ($service)
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
order to set the original request, URI, and response instances as request
attributes for inner layers. See the [migration chapter](migration.md#original-request-response-and-uri)
for more details.

## Middleware Decorators

Within `MiddlewarePipe`, if a response prototype is composed, callable
middleware piped to the instance will be wrapped in a decorator.

Two versions exist:

- `Zend\Stratigility\Middleware\CallableMiddlewareWrapper` will wrap a callable
  using the legacy interface; as such, it also requires a response instance:

  ```php
  $middleware = new CallableMiddlewareWrapper($middleware, $response);
  ```

- `Zend\Stratigility\Middleware\CallableMiddlewareWrapper` will wrap a callable
  that defines exactly two arguments, with the second type-hinting on the
  http-interop/http-middleware `DelegateInterface`:

  ```php
  $middleware = new CallableMiddlewareWrapper(
    function ($request, DelegateInterface $delegate) {
        // ... 
    }
  );
  ```

You can manually decorate callable middleware using these decorators, or simply
let `MiddlewarePipe` do the work for you. To let `MiddlewarePipe` handle this,
however, you _must_ compose a response prototype prior to piping middleware
using the legacy middleware signature.

## Delegates

In addition to `Zend\Stratigility\Next`, Stratigility provides another
http-interop/http-middleware `DelegateInterface` implementation,
`Zend\Stratigility\Delegate\CallableDelegateDecorator`.

This class can be used to wrap a callable `$next` instance for use in passing to
an http-interop/http-middleware middleware interface `process()` method as a
delegate; the primary use case is adapting functor middleware to work as
http-interop middleware.

As an example:

```php
// http-interop/http-middleware 0.2:
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

// http-interop/http-middleware 0.4.1:
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;

class TimestampMiddleware implements ServerMiddlewareInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        return $this->process($request, new CallableDelegateDecorator($next, $response));
    }

    public function process(
        ServerRequestInterface $request,
        DelegateInterface $delegate
    ) {
        $response = $delegate->process($request);
        return $response->withHeader('X-Processed-Timestamp', time());
    }
}
```
