# API Reference

The following make up the primary API of Stratigility.

## Middleware

`Zend\Stratigility\MiddlewarePipe` is the primary application interface, and has been discussed
previously. Its API is:

```php
class MiddlewarePipe implements MiddlewareInterface
{
    public function pipe(string|callable $path, callable $middleware = null);
    public function __invoke(
        Psr\Http\Message\ServerRequestInterface $request,
        Psr\Http\Message\ResponseInterface $response,
        callable $next
    ) :  Psr\Http\Message\ResponseInterface;
}
```

`pipe()` takes up to two arguments. If only one argument is provided, `$middleware` will be assigned
that value, and `$path` will be re-assigned to the value `/`; this is an indication that the
`$middleware` should be invoked for any path. If `$path` is provided, the `$middleware` will only be
executed for that path and any subpaths.

Middleware is executed in the order in which it is piped to the `MiddlewarePipe` instance.

`__invoke()` is itself middleware. `$next` should have the following signature:

```php
function (
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response
) : Psr\Http\Message\ResponseInterface
```

Most often, you can pass an instance of `Zend\Stratigility\NoopFinalHandler` for
`$next` if invoking a middleware pipeline manually; otherwise, a suitable
callback will be provided for you (typically an instance of
`Zend\Stratigility\Next`, which `MiddlewarePipe` creates internally before
dispatching to the various middleware in its pipeline).

Middleware should either return a response, or the result of `$next()` (which
should eventually evaluate to a response instance).

## Next

`Zend\Stratigility\Next` is primarily an implementation detail of middleware, and exists to allow
delegating to middleware registered later in the stack. It is implemented as a functor.

Because `Psr\Http\Message`'s interfaces are immutable, if you make changes to your Request and/or
Response instances, you will have new instances, and will need to make these known to the next
middleware in the chain. `Next` expects these arguments for every invocation.

```php
class Next
{
    public function __invoke(
        Psr\Http\Message\ServerRequestInterface $request,
        Psr\Http\Message\ResponseInterface $response
    ) : Psr\Http\Message\ResponseInterface;
}
```

You should **always** either capture or return the return value of `$next()` when calling it in your
application. The expected return value is a response instance, but if it is not, you may want to
return the response provided to you.

The following are examples demonstrating usage of `Next` within middleware.

### Providing an altered request:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);
    return $next(
        $request->withBodyParams($bodyParams), // Next will pass the new
        $response                              // request instance
    );
}
```

### Providing an altered response:

```php
function ($request, $response, $next)
{
    $updated = $response->withAddedHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $next($request, $updated);
}
```

> ### Do not pass an altered response
>
> Passing an altered response seems like a good idea. However, this means that
> a deeper layer within the application could return a completely new response,
> losing any changes you injected.
>
> As such, we recommend operating only on the response returned by invoking
> `$next()`, or returning a brand new response instance entirely.

### Providing both an altered request and response:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $updated = $response->withAddedHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $next(
        $request->withBodyParams($bodyParser($request)),
        $updated
    );
}
```

> ### Do not pass an altered response
>
> As noted in the example immediately above, we recommend only operating
> on the response returned by `$next()`, or returning a new response
> instance.

### Returning a response to complete the request

If you have no changes to the response, and do not want further middleware in
the pipeline to execute, do not call `$next()` and simply return a response from
your middleware.

```php
function ($request, $response, $next)
{
    $response = $response->withAddedHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $response;
}
```

One caveat: if you are in a nested middleware or not the first in the stack, all parent and/or
previous middleware must also call `return $next(/* ... */)` for this to work correctly.

As such, _we recommend always returning `$next()` when invoking it in your middleware_:

```php
return $next(/* ... */);
```

And, if not calling `$next()`, returning the response instance:

```php
return $response;
```

## HTTP Messages

### Zend\Stratigility\Http\Request

`Zend\Stratigility\Http\Request` acts as a decorator for a `Psr\Http\Message\ServerRequestInterface`
instance. The primary reason is to allow composing middleware such that you always have access to
the original request instance.

As an example, consider the following:

```php
$app1 = new Middleware();
$app1->pipe('/foo', $fooCallback);

$app2 = new Middleware();
$app2->pipe('/root', $app1);

$server = Server::createServer($app2 /* ... */);
```

In the above, if the URI of the original incoming request is `/root/foo`, what `$fooCallback` will
receive is a URI with a past consisting of only `/foo`. This practice ensures that middleware can be
nested safely and resolve regardless of the nesting level.

If you want access to the full URI — for instance, to construct a fully qualified URI to your
current middleware — `Zend\Stratigility\Http\Request` contains a method, `getOriginalRequest()`,
which will always return the original request provided to the application:

```php
function ($request, $response, $next)
{
    $location = $request->getOriginalRequest()->getUri()->getPath() . '/[:id]';
    $response = $response->setHeader('Location', $location);
    $response = $response->setStatus(302);
    return $response;
}
```

### Zend\Stratigility\Http\Response

`Zend\Stratigility\Http\Response` acts as a decorator for a `Psr\Http\Message\ResponseInterface`
instance, and also implements `Zend\Stratigility\Http\ResponseInterface`, which provides the
following convenience methods:

- `write()`, which proxies to the `write()` method of the composed response stream.
- `end()`, which marks the response as complete; it can take an optional argument, which, when
  provided, will be passed to the `write()` method. Once `end()` has been called, the response is
  immutable and will throw an exception if a state mutating method like `withHeader` is called.
- `isComplete()` indicates whether or not `end()` has been called.

Additionally, it provides access to the original response created by the server via the method
`getOriginalResponse()`.
