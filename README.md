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
$ composer require "psr/http-message:~1.0-dev@dev" "phly/http:~1.0-dev@dev" "phly/conduit:~1.0-dev@dev"
```

Conduit has the following dependencies (which are managed by Composer):

- `phly/http`, which provides implementations of the [proposed PSR HTTP message interfaces](https://github.com/php-fig/fig-standards/blob/master/proposed/http-message.md), as well as a "server" implementation similar to [node's http.Server](http://nodejs.org/api/http.html); this is the foundation on which Conduit is built.
- `zendframework/zend-escaper`, used by the `FinalHandler` for escaping error messages prior to passing them to the response.

You can provide your own request and response implementations if desired, but stream-based implementations are provided in this package.

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
$server = Server::createServer($app, $_SERVER);
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
$server = Server::createServer($app, $_SERVER);

// Landing page
$app->pipe('/', function ($req, $res, $next) {
    if ($req->getUrl()->path !== '/') {
        return $next();
    }
    $res->end('Hello world!');
});

// Another page
$app->pipe('/foo', function ($req, $res, $next) {
    $res->end('FOO!');
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

Another way to create middleware is to write a callable capable of receiving minimally a request and a response object, and optionally a callback to call the next in the chain. In this callback, you can handle as much or as little of the request as you want -- including delegating to other handlers. If your middleware also accepts a `$next` argument, if it is unable to complete the request, or allows further processing, it can call it to return handling to the parent middleware.

As an example, consider the following middleware which will use an external router to map the incoming request path to a handler; if unable to map the request, it returns processing to the next middleware.

```php
$app->pipe(function ($req, $res, $next) use ($router) {
    $path = $req->getUrl()->path;

    // Route the path
    $route = $router->route($path);
    if (! $route) {
        return $next();
    }

    $handler = $route->getHandler();
    $handler($req, $res, $next);
});
```

Middleware written in this way can be any of the following:

- Closures (as shown above)
- Functions
- Static class methods
- PHP array callbacks (e.g., `[ $dispatcher, 'dispatch' ]`, where `$dispatcher` is a class instance)
- Invokable PHP objects (i.e., instances of classes implementing `__invoke()`)
- PHP objects implementing a `handle()` instance method

In all cases, if you wish to implement typehinting, the signature is:

```php
function (
    Psr\Http\Message\RequestInterface $request,
    Phly\Http\ResponseInterface $response,
    callable $next = null
) {
}
```

Error handler middleware has the following signature:

```php
function (
    $error, // Can be any type
    Psr\Http\Message\RequestInterface $request,
    Phly\Http\ResponseInterface $response,
    callable $next
) {
}
```

Another approach is to extend the `Phly\Conduit\Middleware` class itself -- particularly if you want to allow attaching other middleware to your own middleware. In such a case, you will generally override the `handle()` method to perform any additional logic you have, and then call on the parent in order to iterate through your stack of middleware:

```php
use Phly\Conduit\Middleware;
use Phly\Http\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

class CustomMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next = null)
    {
        // perform some work...

        // delegate to parent
        parent::handle($request, $response, $next);

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

`Phly\Conduit\Middleware` is the primary application interface, and has been discussed previously. It's API is:

```php
class Middleware
{
    public function pipe($path, $handler = null);
    public function handle(
        Psr\Http\Message\RequestInterface $request = null,
        Phly\Http\ResponseInterface $response = null,
        callable $out = null
    );
}
```

`pipe()` takes up to two arguments. If only one argument is provided, `$handler` will be assigned that value, and `$path` will be re-assigned to the value `/`; this is an indication that the `$handler` should be invoked for any path. If `$path` is provided, the `$handler` will only be executed for that path and any subpaths.

Handlers are executed in the order in which they are piped to the `Middleware` instance.

`handle()` is itself a middleware handler. If `$out` is not provided, an instance of `Phly\Conduit\FinalHandler` will be created, and used in the event that the pipe stack is exhausted.

### FinalHandler

`Phly\Conduit\FinalHandler` is a default implementation of middleware to execute when the stack exhausts itself. It is provided the request and response object to the constructor, and expects zero or one arguments when invoked; one argument indicates an error condition.

`FinalHandler` allows an optional third argument during instantiation, `$options`, an array of options with which to configure itself. These options currently include:

- env, the application environment. If set to "production", no stack traces will be provided.
- onerror, a callable to execute if an error is passed when `FinalHandler` is invoked. The callable is invoked with the error, the request, and the response.
