# Creating Middleware

To create middleware, write a callable capable of receiving minimally PSR-7
ServerRequest and Response objects, and a callback to call the next middleware
in the chain. In your middleware, you can handle as much or as little of the
request as you want, including delegating to other middleware in order to
produce or return a response.

As an example, consider the following middleware which will use an external
router to map the incoming request path to a handler; if unable to map the
request, it returns processing to the next middleware.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;

function ($req, DelegateInterface $delegate) use ($router) {
    $path = $req->getUri()->getPath();

    // Route the path
    $route = $router->route($path);
    if (! $route) {
        return $delegate->process($req);
    }

    /** @var MiddlewareInterface $handler */
    $handler = $route->getHandler();
    return $handler->process($req, $delegate);
}
```

Middleware written in this way can be any of the following:

- Closures (as shown above)
- Functions
- Static class methods
- PHP array callbacks (e.g., `[ $dispatcher, 'dispatch' ]`, where `$dispatcher` is a class instance)
- Invokable PHP objects (i.e., instances of classes implementing `__invoke()`)
- Objects implementing `Interop\Http\ServerMiddleware\MiddlewareInterface`

In all cases, if you wish to implement typehinting, the signature is:

```php
function (
    Psr\Http\Message\ServerRequestInterface $request,
    Interop\Http\ServerMiddleware\DelegateInterface $delegate
) : Psr\Http\Message\ResponseInterface
```
