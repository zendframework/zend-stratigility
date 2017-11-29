# Creating Middleware in Version 3

In Stratigility 3.0 middleware has to be PSR-15 compatible, it means
it must implement `MiddlewareInterface`. Please see the following
example:

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
