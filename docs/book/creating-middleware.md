# Creating Middleware

Stratigility provides several ways to write middleware:

- By implementing a standard interface.
- By writing a PHP callable that uses the same signature as the standard
  interface.
- By writing a PHP callable accepting standard PSR-7 messages.

This document catalogs each of these, and includes pros and cons for each
pattern.

In all cases, middleware can do each (or all!) of the following:

- Examine a request, and return a response if certain requirements
  are (or are not!) met.
- Delegate handling (and thus response generation) to the next layer.
- Manipulate the response returned by a lower layer, and return the modified
  version.

With each of the types below, we will demonstrate the same example: using an
external router instance to attempt to route a request and delegate to the
middleware matched.

## MiddlewareInterface

- Since 1.3.0

The [PHP-FIG standards body](http://www.php-fig.org) identifies and ratifies
standards for community use. One of these,
[PSR-7](http://www.php-fig.org/psr/psr-7/) is used by Stratigility to provide
standard HTTP message interfaces.

Another, the proposed [PSR-15 (HTTP Server Request
Handlers)](https://github.com/php-fig/fig-standards/tree/4b417c91b89fbedaf3283620ce432b6f51c80cc0/proposed/http-handlers),
defines interfaces for handling and producing these messages.

The specification has undergone several revisions via its working group, and
this version of Stratigility supports the following:

- [http-interop/http-middleware v0.4.1](https://github.com/http-interop/http-middleware/releases/tag/0.4.1)
- [http-interop/http-middleware v0.5.0](https://github.com/http-interop/http-middleware/releases/tag/0.5.0)

The two use different namespaces, and the intermediary interface is named
differently between the two (and defines a different method).

If you are new to Stratigility, we suggest using the latest version of the spec,
as it is closest to how the final PSR-15 specificaion defines the interfaces,
and will only require changing the namespace from which you import the
interfaces later. If you are upgrading, however, choose the 0.4.1 version to
retain existing compatibility.

When writing middleware targeting http-middleware 0.4.1, define your middleware
as follows:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class MyMiddleware implements MiddlewareInterface
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $path = $request->getUri()->getPath();

        // Route the path
        $route = $this->router->route($path);
        if (! $route) {
            return $delegate->process($request);
        }

        $middleware = $route->getHandler();
        return $middleware->process($request, $delegate);
    }
}
```

Under http-middleware 0.5.0, the example becomes the following:

```php
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class MyMiddleware implements MiddlewareInterface
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        $path = $request->getUri()->getPath();

        // Route the path
        $route = $this->router->route($path);
        if (! $route) {
            return $handler->handle($request);
        }

        $middleware = $route->getHandler();
        return $middleware->process($request, $delegate);
    }
}
```

Note that the primary difference is the change from `DelegateInterface` to
`RequestHandlerInterface`; also note that the method each defines is different
(`process` vs `handle`).

## Callable standards-signature middleware

- Since 1.3.0: `CallableInteropMiddlewareWrapper`
- Since 2.2.0: `CallableMiddlewareDecorator`
- Deprecated since 2.2.0: `CallableInteropMiddlewareWrapper`

You may also write PHP callables that fulfill the http-middleware interface
signatures as defined in the previous section.

If your middleware satisfies the http-interop 0.4.1 signature (which, in this
case, means that it expects a `DelegateInterface`, and will call its `process()`
method), use `CallableInteropMiddlewareWrapper`:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper;

$pipeline->pipe(new CallableInteropMiddlewareWrapper(
    function ($request, $delegate) use ($router) {
        $path = $request->getUri()->getPath();

        // Route the path
        $route = $router->route($path);
        if (! $route) {
            return $delegate->process($request);
        }

        $middleware = $route->getHandler();
        return $middleware->process($request, $delegate);
    }
))
```

Starting in 2.0, when you pipe such middleware directly to `MiddlewarePipe`, it
internally decorates it for you using this class.

> ### CallableInteropMiddlewareWrapper deprecated
>
> The `CallableInteropMiddlewareWrapper` is deprecated starting in version
> 2.2.0, and will be removed entirely for version 3.0.0. We recommend updating
> your code to use http-middleware 0.5.0 and the `CallableMiddlewareDecorator`
> to make your code future-proof.

If your middleware satisfies the http-interop 0.5.0 signature (which, in this
case, means that it expects a `RequestHandlerInterface`, and will call its
`handle()` method), use `CallableMiddlewareDecorator`:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Stratigility\Middleware\CallableMiddlewareDecorator;

$pipeline->pipe(new CallableMiddlewareDecorator(
    function ($request, $handler) use ($router) {
        $path = $request->getUri()->getPath();

        // Route the path
        $route = $router->route($path);
        if (! $route) {
            return $handler->handle($request);
        }

        $middleware = $route->getHandler();
        return $middleware->process($request, $delegate);
    }
))
```

Starting in 2.2.0, when you pipe such middleware directly to `MiddlewarePipe`,
it internally decorates it for you using this class.

## Double Pass Middleware

- Since 1.0.0: piping double-pass middleware directly
- Since 1.3.0: `CallableMiddlewareWrapper` since 1.3.0; deprecated in 2.2.0
- Since 2.2.0: `DoublePassMiddlewareDecorator`
- Deprecated since 2.2.0: piping double-pass middleware directly
- Deprecated since 2.2.0: `CallableMiddlewareWrapper`

The last style of middleware is called "double pass" middleware.

The signature of such middleware is as follows:

```php
function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next
)
```

where the callable is expected to return a PSR-7 `ResponseInterface`, and where
`$next` has the following signature:

```php
function (
    ServerRequestInterface $request,
    ResponseInterface $response
)
```

and is also expected to return a response.

This latter function is the basis for the name "double pass"; you pass _both_ a
request _and_ a response to the next layer.

Neither the callable arguments nor the return value need typehints, though we
recommend them for type safety.

In versions prior to 2.0, you could pipe double-pass middleware directly to the
pipeline:

```php
$pipeline->pipe(function ($request, $response, $next) {});
```

While this usage is still possible in the v2 series, we recommend against using
it for two reasons:

- Version 3 will no longer support direct piping of such middleware.
- The signature does not follow the PSR-15 standards, making the middleware
  non-portable to other PSR-15 middleware dispatcher stacks.

As such, we provide options for you to decorate such middleware.

> ### Do not operate on the response
>
> If you are creating double pass middleware, do not use the `$response`
> argument passed to the middleware as anything other than a prototype
> from which to build a response to return from the method.
>
> If you manipulate the response before passing it to the next layer, the next
> layer may choose to return a completely different response; in the case of
> standards-based middleware, it will never even receive the instance!
>
> If changes to the response are necessary, operate only on the response
> _returned_ by the next layer.

### CallableMiddlewareWrapper

- Since 1.3.0
- Deprecated since 2.2.0

Our first double-pass middleware decorator is
`Zend\Stratigility\Middleware\CallableMiddlewareWrapper`, which implements the
http-middleware 0.4.1 `MiddlewareInterface`:

```php
$pipeline->pipe(new CallableMiddlewareWrapper(
    function ($request, $response, $next) {},
    $responsePrototype
));
```

The `$responsePrototype` argument is required, as without it, there is no
response instance to pipe to the decorated middleware.

Starting in 2.0, if you pipe such middleware directly to `MiddlewarePipe`, it
internally decorates it for you, as long as the pipeline also composes a
response prototype:

```php
$pipeline->setResponsePrototype(new Response());
$pipeline->pipe($doublePassMiddleware);
```

We recommend always decorating the middleware manually. We also recommend
migrating to the `DoublePassMiddlewareDecorator` to make your code forwards
compatible with version 3.

### DoublePassMiddlewareDecorator

- Since 2.2.0

`Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator` implements the
http-middleware 0.5.0 `MiddlewareInterface`, and will be updated in version 3 to
implement the PSR-15 `MiddlewareInterface`:

```php
$pipeline->pipe(new CallableMiddlewareWrapper(
    function ($request, $response, $next) {},
    $responsePrototype
));
```

As with the `CallableMiddlewareWrapper`, the `$responsePrototype` argument is
required, as without it, there is no response instance to pipe to the decorated
middleware.

Starting in version 2.2.0, piping double pass middleware directly to
`MiddlewarePipe` internally decorates it using the
`DoublePassMiddlewareDecorator` internally, so long as you have also already
composed a response prototype in the `MiddlewarePipe` instance.
