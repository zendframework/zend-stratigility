# Middleware

What is middleware?

Middleware is code that exists between the request and response, and which can
take the incoming request, perform actions based on it, and either complete the
response or pass delegation on to the next middleware in the queue.

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Server;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\NoopFinalHandler;

require __DIR__ . '/../vendor/autoload.php';

$app = new MiddlewarePipe();
$app->setResponsePrototype(new Response());

$server = Server::createServer($app, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

// Landing page
$app->pipe('/', function ($req, DelegateInterface $delegate) {
    if (! in_array($req->getUri()->getPath(), ['/', ''], true)) {
        return $delegate->process($req);
    }

    $response = new Response();
    $response->getBody()->write('Hello world!');
    return $response;
});

// Another page
$app->pipe('/foo', function ($req, DelegateInterface $delegate) {
    $response = new Response();
    $response->getBody()->write('FOO!');
    return $response;
});

$server->listen(new NoopFinalHandler());
```

In the above example, we have two examples of middleware. The first is a
landing page, and listens at the root path. If the request path is empty or
`/`, it completes the response. If it is not, it delegates to the next
middleware in the stack. The second middleware matches on the path `/foo`
&mdash; meaning it will match `/foo`, `/foo/`, and any path beneath. In that
case, it will complete the response with its own message. If no paths match at
this point, a "final handler" is composed by default to report 404 status.

So, concisely put, _middleware are PHP callables that accept a request object,
and do something with it_.

> ### http-interop middleware
>
> The above example demonstrates the using the interfaces from the http-interop
> project. http-interop is a project attempting to standardize middleware signatures.
> The signature of the 0.4 series server-side middleware, on which Stratigility
> 2.X is based, is:
>
> ```php
> namespace Interop\Http\ServerMiddleware;
>
> use Psr\Http\Message\ResponseInterface;
> use Psr\Http\Message\ServerRequestInterface;
>
> interface MiddlewareInterface
> {
>     public function process(
>         ServerRequestInterface $request,
>         DelegateInterface $delegate
>     ) : ResponseInterface;
> }
>
> interface DelegateInterface
> {
>     public function process(
>         ServerRequestInterface $request
>     ) : ResponseInterface;
> }
> ```
>
> Stratigility allows you to implement the http-interop/http-middleware
> middleware interface to provide middleware.  Additionally, you can define
> `callable` middleware with the following signature, and it will be dispatched
> as http-interop middleware:
>
> ```php
> function(
>     ServerRequestInterface $request,
>     DelegateInterface $delegate
> ) : ResponseInterface;
> ```
>
> (The `$request` argument does not require a typehint when defining callable
> middleware, but we encourage its use.)
>
> Finally, to keep backwards compatibility with Stratigility v1, as well as
> other projects that have not yet adopted http-interop, we allow using the
> "double-pass" signature (so-called because you pass both a request and response
> object to the delegate):
>
> ```php
> function (
>     ServerRequestInterface $request,
>     ResponseInterface $response,
>     callable $next
> ) : ResponseInterface
> ```
>
> where `$next` is expected to have the following signature:
>
> ```php
> function (
>     ServerRequestInterface $request,
>     ResponseInterface $response
> ) : ResponseInterface
> ```
>
> As such, the above example can also be written as follows:
>
> ```php
> $app->pipe('/', function ($request, $response, $next) {
>     if (! in_array($request->getUri()->getPath(), ['/', ''], true)) {
>         return $next($request, $response);
>     }
>     return new TextResponse('Hello world!');
> });
> ```

Middleware can decide more processing can be performed by calling the `$next`
callable (or, when defining http-interop middleware, `$delegate`) passed during
invocation. With this paradigm, you can build a workflow engine for handling
requests &mdash; for instance, you could have middleware perform the following:

- Handle authentication details
- Perform content negotiation
- Perform HTTP negotiation
- Route the path to a more appropriate, specific handler

Each middleware can itself be middleware, and can attach to specific paths,
allowing you to mix and match applications under a common domain. As an
example, you could put API middleware next to middleware that serves its
documentation, next to middleware that serves files, and segregate each by URI:

```php
$app->pipe('/api', $apiMiddleware);
$app->pipe('/docs', $apiDocMiddleware);
$app->pipe('/files', $filesMiddleware);
```

The handlers in each middleware attached this way will see a URI with that path
segment stripped, allowing them to be developed separately and re-used under
any path you wish.

Within Stratigility, middleware can be:

- Any PHP callable that accepts, minimally, a
  [PSR-7](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md)
  ServerRequest and Response (in that order), and, optionally, a callable (for
  invoking the next middleware in the queue, if any).
- Any [http-interop 0.4.1 - middleware](https://github.com/http-interop/http-middleware/tree/0.4.1).
  `Zend\Stratigility\MiddlewarePipe` implements
  `Interop\Http\ServerMiddleware\MiddlewareInterface`. (Stratigility 2.0 series.)
- Any [http-interop 0.5.0 - middleware](https://github.com/http-interop/http-middleware/tree/0.5.0).
  `Zend\Stratigility\MiddlewarePipe` implements
  `Interop\Http\Server\MiddlewareInterface`. (Since Stratigility 2.1)
