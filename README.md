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
$ composer require "psr/http-message:~0.5.1@dev" "phly/http:~1.0-dev@dev" "phly/conduit:~1.0-dev@dev"
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

- Create middleware
- Create a server, using the middleware
- Instruct the server to listen for a request

```php
use Phly\Conduit\Middleware;
use Phly\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$app    = new Middleware();
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

Middleware is code that exists between the request and response, and which can take the incoming request, perform actions based on it, and either complete the response or pass delegation on to the next middleware in the stack.

```php
use Phly\Conduit\Middleware;
use Phly\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$app    = new Middleware();
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

Error Handlers
--------------

To handle errors, you can write middleware that accepts **exactly** four arguments:

```php
function ($error, $request, $response, $next) { }
```

As the stack is executed, if `$next()` is called with an argument, or if an exception is thrown, middleware will iterate through the stack until the first such error handler is found. That error handler can either complete the request, or itself call `$next()`. **Error handlers that call `$next()` SHOULD call it with the error it received itself, or with another error.**

Error handlers are usually attached at the end of middleware, to prevent attempts at executing non-error-handling middleware, and to ensure they can intercept errors from any other handlers.

Creating Middleware
-------------------

The easiest way to create middleware is to either instantiate a `Phly\Conduit\Middleware` instance and attach handlers to it. Attach your middleware instance to the primary application when done.

```php
$api = new Middleware();
$api->pipe(/* ... */); // repeat as necessary

$app = new Middleware();
$app->pipe('/api', $api);
```

Another way to create middleware is to write a callable capable of receiving minimally a request and a response object, and optionally a callback to call the next in the chain. In your middleware callable, you can handle as much or as little of the request as you want -- including delegating to other handlers. If your middleware also accepts a `$next` argument, if it is unable to complete the request, or allows further processing, it can call it to return handling to the parent middleware.

As an example, consider the following middleware which will use an external router to map the incoming request path to a handler; if unable to map the request, it returns processing to the next middleware.

```php
$app->pipe(function ($req, $res, $next) use ($router) {
    $path = parse_url($req->getUrl(), PHP_URL_PATH);

    // Route the path
    $route = $router->route($path);
    if (! $route) {
        return $next();
    }

    $handler = $route->getHandler();
    return $handler($req, $res, $next);
});
```

Middleware written in this way can be any of the following:

- Closures (as shown above)
- Functions
- Static class methods
- PHP array callbacks (e.g., `[ $dispatcher, 'dispatch' ]`, where `$dispatcher` is a class instance)
- Invokable PHP objects (i.e., instances of classes implementing `__invoke()`)

In all cases, if you wish to implement typehinting, the signature is:

```php
function (
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    callable $next = null
) {
}
```

Error handler middleware has the following signature:

```php
function (
    $error, // Can be any type
    Psr\Http\Message\ServerRequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    callable $next
) {
}
```

Another approach is to extend the `Phly\Conduit\Middleware` class itself -- particularly if you want to allow attaching other middleware to your own middleware. In such a case, you will generally override the `__invoke()` method to perform any additional logic you have, and then call on the parent in order to iterate through your stack of middleware:

```php
use Phly\Conduit\Middleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CustomMiddleware extends Middleware
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

Another approach using this method would be to override the constructor to add in specific middleware, perhaps using configuration provided. In this case, make sure to also call `parent::__construct()` to ensure the middleware stack is initialized; I recommend doing this as the first action of the method.

```php
use Phly\Conduit\Middleware;

class CustomMiddleware extends Middleware
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

`Phly\Conduit\Middleware` is the primary application interface, and has been discussed previously. Its API is:

```php
class Middleware
{
    public function pipe($path, $handler = null);
    public function __invoke(
        Psr\Http\Message\ServerRequestInterface $request = null,
        Psr\Http\Message\ResponseInterface $response = null,
        callable $out = null
    );
}
```

`pipe()` takes up to two arguments. If only one argument is provided, `$handler` will be assigned that value, and `$path` will be re-assigned to the value `/`; this is an indication that the `$handler` should be invoked for any path. If `$path` is provided, the `$handler` will only be executed for that path and any subpaths.

Handlers are executed in the order in which they are piped to the `Middleware` instance.

`__invoke()` is itself a middleware handler. If `$out` is not provided, an instance of `Phly\Conduit\FinalHandler` will be created, and used in the event that the pipe stack is exhausted.

### Next

`Phly\Conduit\Next` is primarily an implementation detail of middleware, and exists to allow delegating to middleware registered later in the stack.

Because `Psr\Http\Message`'s interfaces are immutable, if you make changes to your Request and/or Response instances, you will have new instances, and will need to make these known to the next middleware in the chain. `Next` allows this by allowing the following argument combinations:

- `Next()` will re-use the currently registered Request and Response instances.
- `Next(RequestInterface $request)` will register the provided `$request` with itself, and that instance will be used for subsequent invocations.
- `Next(ResponseInterface $response)` will register the provided `$response` with itself, and that instance will be used for subsequent invocations.  provided `$response` will be returned.
- `Next(RequestInterface $request, ResponseInterface $response)` will register each of the provided `$request` and `$response` with itself, and those instances will be used for subsequent invocations.
- If any other argument is provided for the first argument, it is considered the error to report and pass to registered error middleware. If an error provided, the second argument may be either a request instance or a response instance; if the second argument is a request instance, a response instance may be passed as the third argument.

Note: you **can** pass an error as the first argument and a response as the second, and `Next` will reset the response in that condition as well.

As examples:

#### Providing an altered request:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $bodyParams = $bodyParser($request);
    $request = $request->setBodyParams($bodyParams);
    return $next($request); // Next will now register this altered request
                            // instance
}
```

#### Providing an altered response:

```php
function ($request, $response, $next)
{
    $response = $response->addHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $next($response); // Next will now register this altered
                                   // response instance
}
```

#### Providing both an altered request and response:

```php
function ($request, $response, $next) use ($bodyParser)
{
    $request  = $request->setBodyParams($bodyParser($request));
    $response = $response->addHeader('Cache-Control', [
        'public',
        'max-age=18600',
        's-maxage=18600',
    ]);
    return $next($request, $response);
}
```

#### Returning a response to complete the request

If you want to complete the request, don't call `$next()`. However, if you have modified, populated, or created a response that you want returned, you can return it from your middleware, and that value will be returned on the completion of the current iteration of `$next()`.

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

#### Raising an error condition

```php
function ($request, $response, $next)
{
    try {
        // try some operation...
    } catch (Exception $e) {
        return $next($e); // Next registered error middleware will be invoked
    }
}
```

#### Raising an error condition with a request and/or response

```php
function ($request, $response, $next)
{
    try {
        // try some operation...
    } catch (Exception $e) {
        $next($e, $request); // Error with updated request; OR
        $next($e, $response); // Error with updated response; OR
        $next($e, $request, $response); // Error with updated request
                                               // AND response
    }
}
```

### FinalHandler

`Phly\Conduit\FinalHandler` is a default implementation of middleware to execute when the stack exhausts itself. It expects three argumets when invoked: an error condition (or `null` for no error), a request instance, and a response instance. It returns a response.

`FinalHandler` allows an optional argument during instantiation, `$options`, an array of options with which to configure itself. These options currently include:

- `env`, the application environment. If set to "production", no stack traces will be provided.
- `onerror`, a callable to execute if an error is passed when `FinalHandler` is invoked. The callable is invoked with the error (which will be `null` in the absence of an error), the request, and the response.

### HTTP Messages

#### Phly\Conduit\Http\Request

`Phly\Conduit\Http\Request` acts as a decorator for a `Psr\Http\Message\ServerRequestInterface` instance. The primary reason is to allow composing middleware to get a request instance that has a "root path".

As an example, consider the following:

```php
$app1 = new Middleware();
$app1->pipe('/foo', $fooCallback);

$app2 = new Middleware();
$app2->pipe('/root', $app1);

$server = Server::createServer($app2 /* ... */);
```

In the above, if the URI of the original incoming request is `/root/foo`, what `$fooCallback` will receive is a URI with a past consisting of only `/foo`. This practice ensures that middleware can be nested safely and resolve regardless of the nesting level.

If you want access to the full URI -- for instance, to construct a fully qualified URI to your current middleware -- `Phly\Conduit\Http\Request` contains a method, `getOriginalRequest()`, which will always return the original request provided:

```php
function ($request, $response, $next)
{
    $location = $request->getOriginalRequest()->getAbsoluteUri() . '/[:id]';
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
