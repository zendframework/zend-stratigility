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
