# Middleware

What is middleware?

Middleware is code that exists between the request and response, and which can
take the incoming request, perform actions based on it, and either complete the
response or pass delegation on to the next middleware in the queue.

```php
use Zend\Diactoros\Response;
use Zend\Diactoros\Server;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Middleware\CallableMiddlewareDecorator;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\NoopFinalHandler;

require __DIR__ . '/../vendor/autoload.php';

$app = new MiddlewarePipe();
$app->setResponsePrototype(new Response());

$server = Server::createServer($app, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
$response = new Response();

// Landing page
$app->pipe(new CallableMiddlewareDecorator(
    function ($request, $handler) use ($response) {
        if (! in_array($req->getUri()->getPath(), ['/', ''], true)) {
            return $handler->handle($request);
        }
        $response->getBody()->write('Hello world!');
        return $response;
    }
));

// Another page
$app->pipe(new PathMiddlewareDecorator(
    '/foo', 
    new CallableMiddlewareDecorator(function ($request, $handler) use ($response) {
        $response->getBody()->write('FOO!');
        return $response;
    })
));

$server->listen(new NoopFinalHandler());
```

In the above example, we have two examples of middleware. The first is a landing
page, and listens to every request. If the request path is empty or `/`, it
completes the response. If it is not, it passes off request handling to the
middleware in the stack. The second middleware matches only on the path `/foo`
&mdash; meaning it will match `/foo`, `/foo/`, and any path beneath. In that
case, it will complete the response with its own message. If no paths match at
this point, a "final handler" is composed by default to report a 404 status.

So, concisely put, _middleware accept a request, and decide if they can handle
it and return a response, or need to delegate to another handler_.

> ### Middleware types
>
> Stratigility allows a number of different types of middleware.
>
> The type demonstrated above is callable middleware based on an
> interface signature that forms the basis of the [proposed PSR-15
standard](https://github.com/php-fig/fig-standards/tree/4b417c91b89fbedaf3283620ce432b6f51c80cc0/proposed/http-handlers).
> 
> Stratigility also supports:
> 
> - Callable "double pass" middleware.
> - Middleware implementing the proposed PSR-15 `MiddlewareInterface`.
> 
> For more details on the various middleware types accepted, including their
> signatures, please read the [chapter on creating middleware](creating-middleware.md).

Middleware can decide more processing can be performed by calling on the
`$handler` argument passed during invocation. With this paradigm, you can build
a workflow engine for handling requests &mdash; for instance, you could have
middleware perform the following:

- Handle authentication details
- Perform content negotiation
- Perform HTTP negotiation
- Route the path to a more appropriate, specific handler

Each middleware can itself be middleware, and can attach to specific paths,
allowing you to mix and match applications under a common domain. As an
example, you could put API middleware next to middleware that serves its
documentation, next to middleware that serves files, and segregate each by URI:

```php
$app->pipe(new PathMiddlewareDecorator('/api', $apiMiddleware));
$app->pipe(new PathMiddlewareDecorator('/docs', $apiDocMiddleware));
$app->pipe(new PathMiddlewareDecorator('/files', $filesMiddleware));
```

The handlers in each middleware attached this way will see a URI with that path
segment stripped, allowing them to be developed separately and re-used under
any path you wish.
