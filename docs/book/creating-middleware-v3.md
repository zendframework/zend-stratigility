# Creating Middleware in Version 3

- Since: 3.0.0alpha1

Starting with version 3.0.0alpha1, middleware piped to a `MiddlewarePipe`
**MUST** implement the http-interop/http-server-middleware interfaces.
As an example:

```php
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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

> TODO: Should be possible to use also callbacks via wrappers?
