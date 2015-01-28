Conduit
=======

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phly/conduit/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phly/conduit/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/phly/conduit/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/phly/conduit/?branch=master)
[![Scrutinizer Build Status](https://scrutinizer-ci.com/g/phly/conduit/badges/build.png?b=master)](https://scrutinizer-ci.com/g/phly/conduit/build-status/master)

Conduit is a port of [Sencha Connect](https://github.com/senchalabs/connect) to PHP. It allows you to build applications out of _middleware_.

Installation and Requirements
-----------------------------

Install this library using composer:

```console
$ composer require "psr/http-message:~0.8.0@dev" "phly/http:~1.0-dev@dev" "phly/conduit:~1.0-dev@dev"
```

Conduit has the following dependencies (which are managed by Composer):

- `phly/http`, which provides implementations of the [proposed PSR HTTP message interfaces](https://github.com/php-fig/fig-standards/blob/master/proposed/http-message.md), as well as a "server" implementation similar to [node's http.Server](http://nodejs.org/api/http.html); this is the foundation on which Conduit is built.
- `zendframework/zend-escaper`, used by the `FinalHandler` for escaping error messages prior to passing them to the response.

You can provide your own request and response implementations if desired as long as they implement the PSR HTTP message interfaces; by default, Conduit uses `phly/http`.

Contributing
------------

- Please write unit tests for any features or bug reports you have.
- Please run unit tests before opening a pull request. You can do so using `./vendor/bin/phpunit`.
- Please run CodeSniffer before opening a pull request, and correct any issues. Use the following to run it: `./vendor/bin/phpcs --standard=PSR2 --ignore=test/Bootstrap.php src test`.

Usage
-----

Creating an application consists of 3 steps:

- Create middleware or a middleware pipeline
- Create a server, using the middleware
- Instruct the server to listen for a request

```php
use Phly\Conduit\MiddlewarePipe;
use Phly\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$app    = new MiddlewarePipe();
$server = Server::createServer($app,
  $_SERVER,
  $_GET,
  $_POST,
  $_COOKIE,
  $_FILES
);
$server->listen();
```

The above example is useless by itself until you pipe middleware into the application.

Middleware
----------

What is middleware?

Middleware is code that exists between the request and response, and which can take the incoming request, perform actions based on it, and either complete the response or pass delegation on to the next middleware in the queue.

```php
use Phly\Conduit\MiddlewarePipe;
use Phly\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$app    = new MiddlewarePipe();
$server = Server::createServer($app, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

// Landing page
$app->pipe('/', function ($req, $res, $next) {
    if (parse_url($req->getUrl(), PHP_URL_PATH) !== '/') {
        return $next();
    }
    return $res->end('Hello world!');
});

// Another page
$app->pipe('/foo', function ($req, $res, $next) {
    return $res->end('FOO!');
});

$server->listen();
```

In the above example, we have two examples of middleware. The first is a landing page, and listens at the path `/`. If the path is an exact match, it completes the response. If it is not, it delegates to the next middleware in the stack. The second middleware matches on the path `/foo` -- meaning it will match `/foo`, `/foo/`, and any path beneath. In that case, it will complete the response with its own message. If no paths match at this point, a "final handler" is composed by default to report 404 status.

So, concisely put, _middleware are PHP callables that accept a request and response object, and do something with it_.

Middleware can decide more processing can be performed by calling the `$next` callable that is passed as the third argument. With this paradigm, you can build a workflow engine for handling requests -- for instance, you could have middleware perform the following:

- Handle authentication details
- Perform content negotiation
- Perform HTTP negotiation
- Route the path to a more appropriate, specific handler

Each middleware can itself be middleware, and can attach to specific paths -- allowing you to mix and match applications under a common domain. As an example, you could put API middleware next to middleware that serves its documentation, next to middleware that serves files, and segregate each by URI:

```php
$app->pipe('/api', $apiMiddleware);
$app->pipe('/docs', $apiDocMiddleware);
$app->pipe('/files', $filesMiddleware);
```

The handlers in each middleware attached this way will see a URI with that path segment stripped -- allowing them to be developed separately and re-used under any path you wish.

Within Conduit, middleware can be:

- Any PHP callable that accepts, minimally, a [PSR-7](https://github.com/php-fig/fig-standards/blob/master/proposed/http-message.md) request and a response (in that order), and, optionally, a callable (for invoking the next middleware in the queue, if any).
- An object implementing `Phly\Conduit\MiddlewareInterface`. `Phly\Conduit\MiddlewarePipe` implements this interface.

Error Handlers
--------------

To handle errors, you can write middleware that accepts **exactly** four arguments:

```php
function ($error, $request, $response, $next) { }
```

Alternately, you can implement `Phly\Conduit\ErrorMiddlewareInterface`.

When using `MiddlewarePipe`, as the queue is executed, if `$next()` is called with an argument, or if an exception is thrown, middleware will iterate through the queue until the first such error handler is found. That error handler can either complete the request, or itself call `$next()`. **Error handlers that call `$next()` SHOULD call it with the error it received itself, or with another error.**

Error handlers are usually attached at the end of middleware, to prevent attempts at executing non-error-handling middleware, and to ensure they can intercept errors from any other handlers.

Creating Middleware
-------------------

To create middleware, write a callable capable of receiving minimally a request and a response object, and optionally a callback to call the next in the chain.  In your middleware, you can handle as much or as little of the request as you want -- including delegating to other middleware. If your middleware accepts a third argument, `$next`, if it is unable to complete the request, or allows further processing, it can call it to return handling to the parent middleware.

As an example, consider the following middleware which will use an external router to map the incoming request path to a handler; if unable to map the request, it returns processing to the next middleware.

```php
function ($req, $res, $next) use ($router) {
    $path = parse_url($req->getUrl(), PHP_URL_PATH);

    // Route the path
    $route = $router->route($path);
    if (! $route) {
        return $next();
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
- Objects implementing `Phly\Conduit\MiddlewareInterface` (including `Phly\Conduit\MiddlewarePipe`)

In all cases, if you wish to implement typehinting, the signature is:

```php
function (
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    callable $next = null
) {
}
```

The implementation Conduit offers also allows you to write specialized error handler middleware. The signature is the same as for normal middleware, except that it expects an additional argument prepended to the signature, `$error`.  (Alternately, you can implement `Phly\Conduit\ErrorMiddlewareInterface`.) The signature is:

```php
function (
    $error, // Can be any type
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    callable $next
) {
}
```

Executing and composing middleware
----------------------------------

The easiest way to execute middleware is to write closures and attach them to a `Phly\Conduit\MiddlewarePipe` instance. You can nest `MiddlewarePipe` instances to create groups of related middleware, and attach them using a base path so they only execute if that path is matched.

```php
$api = new MiddlewarePipe();  // API middleware collection
$api->pipe(/* ... */);        // repeat as necessary

$app = new MiddlewarePipe();  // Middleware representing the application
$app->pipe('/api', $api);     // API middleware attached to the path "/api"
```


Another approach is to extend the `Phly\Conduit\MiddlewarePipe` class itself -- particularly if you want to allow attaching other middleware to your own middleware. In such a case, you will generally override the `__invoke()` method to perform any additional logic you have, and then call on the parent in order to iterate through your stack of middleware:

```php
use Phly\Conduit\MiddlewarePipe;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CustomMiddleware extends MiddlewarePipe
{
    public function __invoke(Request $request, Response $response, callable $next = null)
    {
        // perform some work...

        // delegate to parent
        parent::__invoke($request, $response, $next);

        // maybe do more work?
    }
}
```

Another approach using this method would be to override the constructor to add in specific middleware, perhaps using configuration provided. In this case, make sure to also call `parent::__construct()` to ensure the middleware queue is initialized; I recommend doing this as the first action of the method.

```php
use Phly\Conduit\MiddlewarePipe;

class CustomMiddleware extends MiddlewarePipe
{
    public function __construct($configuration)
    {
        parent::__construct();

        // do something with configuration ...

        // attach some middleware ...

        $this->pipe(/* some middleware */);
    }
}
```

These approaches are particularly suited for cases where you may want to implement a specific workflow for an application segment using existing middleware, but do not necessarily want that middleware applied to all requests in the application.

API
---

The following make up the primary API of Conduit.

### Middleware

`Phly\Conduit\MiddlewarePipe` is the primary application interface, and has been discussed previously. Its API is:

```php
class MiddlewarePipe implements MiddlewareInterface
{
    public function pipe($path, $middleware = null);
    public function __invoke(
        Psr\Http\Message\ServerRequestInterface $request = null,
        Psr\Http\Message\ResponseInterface $response = null,
        callable $out = null
    );
}
```

`pipe()` takes up to two arguments. If only one argument is provided, `$middleware` will be assigned that value, and `$path` will be re-assigned to the value `/`; this is an indication that the `$middleware` should be invoked for any path. If `$path` is provided, the `$middleware` will only be executed for that path and any subpaths.

Middleware is executed in the order in which it is piped to the `MiddlewarePipe` instance.

`__invoke()` is itself middleware. If `$out` is not provided, an instance of
`Phly\Conduit\FinalHandler` will be created, and used in the event that the pipe
stack is exhausted. The callable should use the same signature as `Next()`:

```php
function (
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response, 
    $err = null
) {
}
```

Internally, `MiddlewarePipe` creates an instance of `Phly\Conduit\Next`, feeding it its queue, executes it, and returns a response.

### Next

`Phly\Conduit\Next` is primarily an implementation detail of middleware, and exists to allow delegating to middleware registered later in the stack.

Because `Psr\Http\Message`'s interfaces are immutable, if you make changes to your Request and/or Response instances, you will have new instances, and will need to make these known to the next middleware in the chain. `Next` expects these arguments for every invocation. Additionally, if an error condition has occurred, you may pass an optional third argument, `$err`, representing the error condition.

```php
class Next
{
    public function __invoke(
        Psr\Http\Message\ServerRequestInterface $request,
        Psr\Http\Message\ResponseInterface $response, 
        $err = null
    );
}
```

You should **always** either capture or return the return value of `$next()` when calling it in your application. The expected return value is a response instance, but if it is not, you may want to return the response provided to you.

As examples:

#### Providing an altered request:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);
    return $next(
        $request->withBodyParams($bodyParams), // Next will pass the new
        $response                              // request instance
    );
}
```

#### Providing an altered response:

```php
function ($request, $response, $next)
{
    $updated = $response->addHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $next(
        $request,
        $updated
    );
}
```

#### Providing both an altered request and response:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $updated = $response->addHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $next(
        $request->withBodyParams($bodyParser($request)),
        $updated
    );
}
```

#### Returning a response to complete the request

If you have no changes to the response, and do not want further middleware in the pipeline to execute, do not call `$next()` and simply return from your middleware. However, it's almost always better and more predictable to return the response instance, as this will ensure it propagates back up to all callers.

```php
function ($request, $response, $next)
{
    $response = $response->addHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $response;
}
```

One caveat: if you are in a nested middleware or not the first in the stack, all parent and/or previous middleware must also call `return $next(/* ... */)` for this to work correctly.

As such, _I recommend always returning `$next()` when invoking it in your middleware_:

```php
return $next(/* ... */);
```

And, if not calling `$next()`, returning the response instance:

```php
return $response
```

#### Raising an error condition

To raise an error condition, pass a non-null value as the third argument to `$next()`:

```php
function ($request, $response, $next)
{
    try {
        // try some operation...
    } catch (Exception $e) {
        return $next($request, $response, $e); // Next registered error middleware will be invoked
    }
}
```

### FinalHandler

`Phly\Conduit\FinalHandler` is a default implementation of middleware to execute when the stack exhausts itself. It expects three arguments when invoked: a request instance, a response instance, and an error condition (or `null` for no error). It returns a response.

`FinalHandler` allows an optional argument during instantiation, `$options`, an array of options with which to configure itself. These options currently include:

- `env`, the application environment. If set to "production", no stack traces will be provided.
- `onerror`, a callable to execute if an error is passed when `FinalHandler` is invoked. The callable is invoked with the error (which will be `null` in the absence of an error), the request, and the response, in that order.

### HTTP Messages

#### Phly\Conduit\Http\Request

`Phly\Conduit\Http\Request` acts as a decorator for a `Psr\Http\Message\ServerRequestInterface` instance. The primary reason is to allow composing middleware such that you always have access to the original request instance.

As an example, consider the following:

```php
$app1 = new Middleware();
$app1->pipe('/foo', $fooCallback);

$app2 = new Middleware();
$app2->pipe('/root', $app1);

$server = Server::createServer($app2 /* ... */);
```

In the above, if the URI of the original incoming request is `/root/foo`, what `$fooCallback` will receive is a URI with a past consisting of only `/foo`. This practice ensures that middleware can be nested safely and resolve regardless of the nesting level.

If you want access to the full URI — for instance, to construct a fully qualified URI to your current middleware — `Phly\Conduit\Http\Request` contains a method, `getOriginalRequest()`, which will always return the original request provided to the application:

```php
function ($request, $response, $next)
{
    $location = $request->getOriginalRequest()->getUri()->getPath() . '/[:id]';
    $response = $response->setHeader('Location', $location);
    $response = $response->setStatus(302);
    return $response;
}
```

#### Phly\Conduit\Http\Response

`Phly\Conduit\Http\Response` acts as a decorator for a `Psr\Http\Message\ResponseInterface` instance, and also implements `Phly\Conduit\Http\ResponseInterface`, which provides the following convenience methods:

- `write()`, which proxies to the `write()` method of the composed response stream.
- `end()`, which marks the response as complete; it can take an optional argument, which, when provided, will be passed to the `write()` method. Once `end()` has been called, the response is immutable.
- `isComplete()` indicates whether or not `end()` has been called.

Additionally, it provides access to the original response created by the server via the method `getOriginalResponse()`.
