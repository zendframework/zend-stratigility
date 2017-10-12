# Creating Middleware

To create middleware, write a callable capable of receiving minimally PSR-7
ServerRequest and Response objects, and a callback to call the next middleware
in the chain. In your middleware, you can handle as much or as little of the
request as you want, including delegating to other middleware. By accepting the
third argument, `$next`, it can allow further processing via invoking that
argument, or return handling to the parent middleware by returning a response.

As an example, consider the following middleware which will use an external
router to map the incoming request path to a handler; if unable to map the
request, it returns processing to the next middleware.

```php
function ($req, $res, $next) use ($router) {
    $path = $req->getUri()->getPath();

    // Route the path
    $route = $router->route($path);
    if (! $route) {
        return $next($req, $res);
    }

    $handler = $route->getHandler();
    return $handler($req, $res, $next);
}
```

Middleware written in this way can be any of the following:

- Closures (as shown above)
- Functions
- Static class methods
- PHP array callbacks (e.g., `[ $dispatcher, 'dispatch' ]`, where `$dispatcher` is a class instance)
- Invokable PHP objects (i.e., instances of classes implementing `__invoke()`)
- Objects implementing `Zend\Stratigility\MiddlewareInterface`

In all cases, if you wish to implement typehinting, the signature is:

```php
function (
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    callable $next
) : Psr\Http\Message\ResponseInterface
```

## http-interop middleware

You can also write your middleware which implements interfaces from
`http-interop/http-middleware`. Stratigility 2.1 supports all version of
http-interop middlewares which are supported by
[`webimpress/http-middleware-compatibility`](https://github.com/webimpress/http-middleware-compatibility).

Here we have an example of http-interop middleware:

```php
// http-interop/http-middleware 0.2:
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

// http-interop/http-middleware 0.4.1:
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;

// http-interop/http-middleware 0.5.0:
use Interop\Http\Server\MiddlewareInterface as ServerMiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface as DelegateInterface;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MyMiddleware implements ServerMiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        DelegateInterface $delegate
    ) : ResponseInterface {
        // ... do something and return response
        // or call delegate:

        // http-interop/http-middleware < 0.5:
        // return $delegate->delegate($request);

        // http-interop/http-middleware 0.5.0:
        // return $delegate->handle($request);
    }
}
```
