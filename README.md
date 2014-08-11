Conduit
=======

Conduit is a port of [Sencha Connect](https://github.com/sencha/connect) to PHP. It allows you to build applications out of _middleware_.

Installation and Requirements
-----------------------------

Install this library using composer:

```console
$ composer require phly/conduit
```

Conduit has the following dependencies (which are managed by Composer):

- `psr/http-message`, which defines interfaces for HTTP messages, including requests and responses. Conduit provides implementations of these, and extends the `ResponseInterface` to provide three additional methods:
  - `write($data)`, to write data to the response body
  - `end($data = null)`, to mark the response as complete, optionally writing data to the body first
  - `isComplete()`, for determining if the response is already complete
- `zendframework/zend-escaper`, used by the `FinalHandler` for escaping error messages prior to passing them to the response.

You can provide your own request and response implementations if desired, but stream-based implementations are provided in this package.

Usage
-----

Creating an application consists of 3 steps:

- Create middleware
- Create a server, using the middleware
- Instruct the server to listen for a request

```php
use Phly\Conduit\Middleware;
use Phly\Conduit\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$app    = new Middleware();
$server = Server::createServer($app);
$server->listen();
```

The above example is useless by itself until you pipe middleware into the application.

Middleware
----------

What is middleware?

Middleware is code that exists between the request and response, and which can take the incoming request, perform actions based on it, and either complete the response or pass delegation on to the next middleware in the stack.

```php
use Phly\Conduit\Middleware;
use Phly\Conduit\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$app    = new Middleware();
$server = Server::createServer($app);

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
    Phly\Conduit\Http\ResponseInterface $response,
    callable $next = null
) {
}
```

Error handler middleware has the following signature:

```php
function (
    $error, // Can be any type
    Psr\Http\Message\RequestInterface $request,
    Phly\Conduit\Http\ResponseInterface $response,
    callable $next
) {
}
```

Another approach is to extend the `Phly\Conduit\Middleware` class itself -- particularly if you want to allow attaching other middleware to your own middleware. In such a case, you will generally override the `handle()` method to perform any additional logic you have, and then call on the parent in order to iterate through your stack of middleware:

```php
use Phly\Conduit\Http\ResponseInterface as Response;
use Phly\Conduit\Middleware;
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

### HTTP

Conduit contains a number of classes and interfaces around the HTTP protocol.

#### Request Message

`Phly\Conduit\Http\Request` implements `Psr\Http\Message\RequestInterface`, and includes the following methods:

```php
class Request
{
    public function __construct($protocol = '1.1', $stream = 'php://input');
    public function addHeader($name, $value);
    public function addHeaders(array $headers);
    public function getBody(); // returns a Stream
    public function getHeader();
    public function getHeaderAsArray();
    public function getHeaders();
    public function getMethod();
    public function getProtocolVersion();
    public function getUrl(); // returns a Uri object
    public function removeHeader($name);
    public function setBody(Psr\Http\Message\StreamInterface $stream);
    public function setHeader($name, $value);
    public function setHeaders(array $headers);
    public function setMethod($method);
    public function setUrl($url); // string or Uri object
}
```

Additionally, `Request` implements property overloading, allowing the developer to set and retrieve arbitrary properties other than those exposed via getters. This allows the ability to pass values between middlewares. As an example, `Middleware` uses this to store the originalUrl passed to the application:

```
$originalUrl = $request->originalUrl;
```

I recommend you store values in properties named after your middleware; use arrays or objects in cases where multiple values may be possible.

#### Response Message

`Phly\Conduit\Http\Response` implements `Phly\Conduit\Http\ResponseInterface`, which extends `Psr\Http\Message\ResponseInterface`, and includes the following methods:

```php
class Response
{
    public function __construct($stream = 'php://input');
    public function addHeader($name, $value);
    public function addHeaders(array $headers);
    public function end($data = null); // Mark the response as complete
    public function getBody(); // returns a Stream
    public function getHeader();
    public function getHeaderAsArray();
    public function getHeaders();
    public function getStatusCode();
    public function getReasonPhrase();
    public function isComplete(); // Is the response complete?
    public function removeHeader($name);
    public function setBody(Psr\Http\Message\StreamInterface $stream);
    public function setHeader($name, $value);
    public function setHeaders(array $headers);
    public function setStatusCode($code);
    public function setReasonPhrase($phrase);
    public function write($data); // Write data to the body
}
```

#### URI

`Phly\Conduit\Http\Uri` models and validates URIs. The request object casts URLs to `Uri` objects, and returns them from `getUrl()`, giving an OOP interface to the parts of a URI. It implements `__toString()`, allowing it to be represented as a string and `echo()`'d directly. The following methods are pertinent:

```php
class Uri
{
    public static function fromArray(array $parts);
    public function __construct($uri);
    public function isValid();
    public function setPath($path);
}
```

`fromArray()` expects an array of URI parts, and should contain 1 or more of the following keys:

- scheme
- host
- port
- path
- query
- fragment

`setPath()` accepts a path, but does not actually change the `Uri` instance; it instead returns a clone of the current instance with the new path.

The following properties are exposed for read-only access:

- scheme
- host
- port
- path
- query
- fragment

#### Stream

`Phly\Conduit\Http\Stream` is an implementation of `Psr\Http\Message\StreamInterface`, and provides a number of facilities around manipulating the composed PHP stream resource. The constructor accepts a stream, which may be either:

- a stream identifier; e.g., `php://input`, a filename, etc.
- a PHP stream resource

If a stream identifier is provided, an optional second parameter may be provided, the file mode by which to `fopen` the stream.

Request objects by default use a `php://input` stream set to read-only; Response objects by default use a `php://memory` with a mode of `wb+`, allowing binary read/write access.

In most cases, you will not interact with the Stream object directly.

#### Server

`Phly\Conduit\Http\Server` represents a server capable of executing middleware. It has two methods:

```php
class Server
{
    public static function createServer(
        Phly\Conduit\Middleware $middleware,
        Psr\Http\Message\RequestInterface $request = null,
        Phly\Conduit\Http\ResponseInterface $response = null
    );
    public function listen(callable $finalHandler = null);
}
```

`createServer()` is used to create an instance of the `Server`. If no request or response objects are provided, defaults are used; in particular, the `Server` contains logic for marshaling request information from the current PHP request environment, including headers, the request URI, the request method, and the request body. If you wish to use your own implementations, pass them to the method when creating your server.

`listen()` executes the middleware. If no `$finalHandler` is provided, an instance of `Phly\Conduit\FinalHandler` is created and used; this callable will be executed if the middleware exhausts its internal stack.

### Middleware

The following make up the primary API of Conduit.

#### Middleware

`Phly\Conduit\Middleware` is the primary application interface, and has been discussed previously. It's API is:

```php
class Middleware
{
    public function pipe($path, $handler = null);
    public function handle(
        Psr\Http\Message\RequestInterface $request = null,
        Phly\Conduit\Http\ResponseInterface $response = null,
        callable $out = null
    );
}
```

`pipe()` takes up to two arguments. If only one argument is provided, `$handler` will be assigned that value, and `$path` will be re-assigned to the value `/`; this is an indication that the `$handler` should be invoked for any path. If `$path` is provided, the `$handler` will only be executed for that path and any subpaths.

Handlers are executed in the order in which they are piped to the `Middleware` instance.

`handle()` is itself a middleware handler. If `$out` is not provided, an instance of `Phly\Conduit\FinalHandler` will be created, and used in the event that the pipe stack is exhausted.

#### FinalHandler

`Phly\Conduit\FinalHandler` is a default implementation of middleware to execute when the stack exhausts itself. It is provided the request and response object to the constructor, and expects zero or one arguments when invoked; one argument indicates an error condition.

`FinalHandler` allows an optional third argument during instantiation, `$options`, an array of options with which to configure itself. These options currently include:

- env, the application environment. If set to "production", no stack traces will be provided.
- onerror, a callable to execute if an error is passed when `FinalHandler` is invoked. The callable is invoked with the error, the request, and the response.
