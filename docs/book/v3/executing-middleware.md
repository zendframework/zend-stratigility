# Executing and composing middleware

The easiest way to execute middleware is to write closures and attach them to a
`Zend\Stratigility\MiddlewarePipe` instance. You can nest `MiddlewarePipe`
instances to create groups of related middleware, and attach them using a base
path so they only execute if that path is matched.

```php
$api = new MiddlewarePipe();  // API middleware collection
$api->pipe(/* ... */);        // repeat as necessary

$app = new MiddlewarePipe();  // Middleware representing the application
$app->pipe(new PathMiddlewareDecorator('/api', $api)); // API middleware attached to the path "/api"
```

> ### Request path changes when path matched
>
> When you use the `PathMiddlewareDecorator` using a path (other than '' or
> '/'), the middleware it decorates is dispatched with a request that strips the
> matched segment(s) from the start of the path. Using the previous example, if
> the path `/api/users/foo` is matched, the `$api` middleware will receive a
> request with the path `/users/foo`. This allows middleware segregated by path to
> be re-used without changes to its own internal routing.

## Handling errors

While the above will give you a basic application, it has no error handling
whatsoever. We recommend adding an initial middleware layer using the
`Zend\Stratigility\Middleware\ErrorHandler` class:

```php
use Zend\Diactoros\Response;
use Zend\Stratigility\Middleware\ErrorHandler;

$app->pipe(new ErrorHandler(new Response());
// Add more middleware...
```

You can learn how to customize the error handler to your needs in the
[chapter on error handlers](error-handlers.md).

## Decorating the MiddlewarePipe

Another approach is to compose a `Zend\Stratigility\MiddlewarePipe` instance
within your own `Interop\Http\Server\MiddlewareInterface` implementation, and
optionally implementing the `RequestHandlerInterface` and/or `pipe()` method.

In such a case, you might define the `process()` method to perform any
additional logic you have, and then call on the decorated `MiddlewarePipe`
instance in order to iterate through your stack of middleware:

```php
class CustomMiddleware implements MiddlewareInterface
{
    private $pipeline;

    public function __construct(MiddlewarePipe $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // perform some work...

        // delegate to parent
        $this->pipeline->process($request, $handler);

        // maybe do more work?
    }
}
```

Another approach using this method would be to override the constructor to add
in specific middleware, perhaps using configuration provided.

```php
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Stratigility\MiddlewarePipe;

class CustomMiddleware implements MiddlewareInterface
{
    private $pipeline;

    public function __construct(array $configuration, MiddlewarePipe $pipeline)
    {
        // do something with configuration ...

        // attach some middleware ...
        $pipeline->pipe(/* some middleware */);

        $this->pipeline = $pipeline;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        /* ... */
    }
}
```

These approaches are particularly suited for cases where you may want to
implement a specific workflow for an application segment using existing
middleware, but do not necessarily want that middleware applied to all requests
in the application.
