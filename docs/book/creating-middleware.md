# Creating Middleware

Middleware piped to a `MiddlewarePipe` **MUST** implement the
http-interop/http-server-middleware interface.

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
