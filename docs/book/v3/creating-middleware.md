# Creating Middleware

Middleware piped to a `MiddlewarePipe` **MUST** implement the
PSR-15 middleware interface.

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MyMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ) : ResponseInterface {
        // ... do something and return response
        // or call request handler:
        // return $handler->handle($request);
    }
}
```

## Anonymous middleware

For one-off middleware, particularly when debugging, you can use an anonymous
class to implement `MiddleareInterface`:

```php
$pipeline->pipe(new class implements MiddlewareInterface {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Clacks-Overhead', 'GNU Terry Pratchett');
    }
});
```

## Callable middleware

Sometimes it's easier to eschew the `MiddlewareInterface`, particularly when
creating a one-off middleware for debugging purposes. In those cases, you can
create a PHP callable that follows the same signature of
`MiddlewareInterface::process()`, and wrap it in a
`Zend\Stratigility\Middleware\CallableMiddlewareDecorator` instance:

```php
$pipeline->pipe(new CallableMiddlewareDecorator(function ($req, $handler) {
    // do some work
    $response = $handler->($req);
    // do some work
    return $response;
});
```

The typehints for the arguments are optional, but such callable middleware will
receive `ServerRequestInterface` and `RequestHandlerInterface` instances,
in that order.

You may also use the `middleware()` utility function in place of `new
CallableMiddlewareDecorator()`.

## Double-Pass middleware

Prior to PSR-15, many PSR-7 frameworks and projects adopted a "double-pass"
middleware definition:

```php
function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next
) : ResponseInterface
```

where `$next` had the signature:

```php
function (
    ServerRequestInterface $request,
    ResponseInterface $response
) : ResponseInterface
```

The latter is the origin of the term "double-pass", as the implementation passes
not a single argument, but two. (The `$response` argument was often used as a
response prototype for middleware that needed to return a response.)

`Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator` allows decorating
such middleware within a PSR-15 `MiddlewareInterface` implementation, allowing
it to be used in your Stratigility application.

When using `DoublePassMiddlewareDecorator`, internally it will decorate the
`$handler` instance as a callable.

To use the decorator, pass it the double-pass middleware to decorate via the
constructor:

```php
$pipeline->pipe(new DoublePassMiddlewareDecorator($middleware));
```

If you are not using zend-diactoros for your PSR-7 implementation, the decorator
also accepts a second argument, a PSR-7 `ResponseInterface` prototype instance
to pass to the double-pass middleware:

```php
$pipeline->pipe(new DoublePassMiddlewareDecorator(
    $middleware,
    $responsePrototype
));
```

You may also use the `doublePassMiddleware()` utility function in place of `new
DoublePassMiddlewareDecorator()`.

> ### Beware of operating on the response
>
> In many cases, poorly written double-pass middleware will manipulate the
> response provided to them and pass the manipulated version to `$next`.
>
> This is problematic if you mix standard PSR-15 and double-pass middleware, as
> the response instance is dropped when `$next` is called, as the decorator we
> provide will ignore the argument.
>
> If you notice such issues appearing, please report them to the project
> providing the double-pass middleware, and ask them to only operate on the
> returned response.
