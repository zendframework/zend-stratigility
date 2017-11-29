# Middleware

What is middleware?

Middleware is code that exists between the request and response, and which can
take the incoming request, perform actions based on it, and either complete the
response or pass delegation on to the next middleware in the queue.

```php
use Zend\Diactoros\Response;
use Zend\Diactoros\Server;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\NoopFinalHandler;

require __DIR__ . '/../vendor/autoload.php';

$app = new MiddlewarePipe();
$app->setResponsePrototype(new Response());

$server = Server::createServer($app, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

// Landing page
$app->pipe('/', function ($req, $res, $next) {
    if (! in_array($req->getUri()->getPath(), ['/', ''], true)) {
        return $next($req, $res);
    }
    $res->getBody()->write('Hello world!');
    return $res;
});

// Another page
$app->pipe('/foo', function ($req, $res, $next) {
    $res->getBody()->write('FOO!');
    return $res;
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

So, concisely put, _middleware are PHP callables that accept a request and
response object, and do something with it_.

> ### http-interop middleware
>
> The above example demonstrates the legacy (pre-1.3.0) signature for
> middleware, which is also widely used across other middleware frameworks
> such as Slim, Relay, Adroit, etc.
>
> http-interop is a project attempting to standardize middleware signatures.
> The signature until the 0.4.0 series for server-side middleware is:
>
> ```php
> namespace Interop\Http\Middleware;
>
> use Psr\Http\Message\ResponseInterface;
> use Psr\Http\Message\ServerRequestInterface;
>
> interface ServerMiddlewareInterface
> {
>     public function process(
>         ServerRequestInterface $request,
>         DelegateInterface $delegate
>     ) : ResponseInterface;
> }
> ```
>
> where `DelegateInterface` is defined as:
>
> ```php
> namespace Interop\Http\Middleware;
>
> use Psr\Http\Message\RequestInterface;
> use Psr\Http\Message\ResponseInterface;
>
> interface DelegateInterface
> {
>     public function process(
>         RequestInterface $request
>     ) : ResponseInterface;
> }
> ```
>
> Starting in http-interop/http-middleware 0.4.1, these become:
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
> (Note the namespace change, the change in the middleware interface name, and
> the change in the `DelegateInterface` signature.)
>
> http-interop/http-middleware 0.5.0 changes the namespace, renames the delegate
> to a request handler, and correspondingly changes the delegation method to
> `handle()`:
>
> ```php
> namespace Interop\Http\Server;
>
> use Psr\Http\Message\ResponseInterface;
> use Psr\Http\Message\ServerRequestInterface;
>
> interface MiddlewareInterface
> {
>     public function process(
>         ServerRequestInterface $request,
>         RequestHandlerInterface $handler
>     ) : ResponseInterface;
> }
>
> interface RequestHandlerInterface
> {
>     public function handle(
>         ServerRequestInterface $request
>     ) : ResponseInterface;
> }
> ```
>
> Stratigility allows you to implement the http-interop/http-middleware
> middleware interface to provide middleware.  Additionally, you can define
> `callable` middleware with the following signature, and it will be dispatched
> as http-interop middleware.
>
> When using http-interop/http-middleware versions prior to 0.5, callable
> middleware will look like this:
>
> ```php
> function(
>     ServerRequestInterface $request,
>     DelegateInterface $delegate
> ) : ResponseInterface;
> ```
>
> When using http-interop/http-middleware versions 0.5.0 and above, it becomes:
>
> ```php
> function(
>     ServerRequestInterface $request,
>     HandlerRequestInterface $handler
> ) : ResponseInterface;
> ```
>
> (In both examples above, the `$request` argument does not require a typehint
> when defining callable middleware, but we encourage its use.)
>
> As such, the above example can also be written as follows:
>
> ```php
> // Using http-interop/http-middleware 0.4.1:
> $app->pipe('/', function ($request, DelegateInterface $delegate) {
>     if (! in_array($request->getUri()->getPath(), ['/', ''], true)) {
>         return $delegate->process($request);
>     }
>     return new TextResponse('Hello world!');
> });
>
> // Using http-interop/http-middleware 0.5.0:
> $app->pipe('/', function ($request, RequestHandlerInterface $handler) {
>     if (! in_array($request->getUri()->getPath(), ['/', ''], true)) {
>         return $handler->handle($request);
>     }
>     return new TextResponse('Hello world!');
> });
> ```

Middleware can decide more processing can be performed by calling the `$next`
callable (or, when defining http-interop middleware, `$delegate`/`$handler`)
passed during invocation. With this paradigm, you can build a workflow engine
for handling requests &mdash; for instance, you could have middleware perform
the following:

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
- Any [http-interop 0.2.0 - middleware](https://github.com/http-interop/http-middleware/tree/0.2.0).
  `Zend\Stratigility\MiddlewarePipe` implements
  `Interop\Http\Middleware\ServerMiddlewareInterface`. (Stratigility 1.3.0 series.)
- Any [http-interop 0.4.1 - middleware](https://github.com/http-interop/http-middleware/tree/0.4.1).
  `Zend\Stratigility\MiddlewarePipe` implements
  `Interop\Http\ServerMiddleware\MiddlewareInterface`. (Stratigility 2.0 series.)
- Any [http-interop 0.5.0 - middleware](https://github.com/http-interop/http-middleware/tree/0.5.0).
  `Zend\Stratigility\MiddlewarePipe` implements
  `Interop\Http\Server\MiddlewareInterface`. (Since Stratigility 2.1)
- An object implementing `Zend\Stratigility\MiddlewareInterface`.
  (Legacy; this interface is deprecated starting in 1.3.0, and removed in
  Stratigility 3.0)
- Any [http-interop/http-server-middleware](https://github.com/http-interop/http-server-middleware).
  `Zend\Stratigility\MiddlewarePipe` implements
  `Interop\Http\Server\MiddlewareInterface`. (Since Stratigility 3.0)
