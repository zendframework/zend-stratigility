# Migrating to version 2

Version 2 of Stratigility makes several breaking changes to the API in
order to provide more flexibility, promote interoperability, and reduce
complexity.

To help you prepare your code for version 2, version 1.3.0 provides several
forwards compatibility features to assist you in the process. However, some
changes will still require changes to your code following the 2.0 release.

## Original request, response, and URI

In the original 1.X releases, Stratigility would decorate the request and
response instances with `Zend\Stratigility\Http\Request` and
`Zend\Stratigility\Http\Response`, respectively. This was done to facilitate
access to the incoming request in cases of nested layers, where the URI path
may have been truncated (`Next` truncates matched paths when executing a layer
if a path was provided when piping the middleware).

Internally, prior to 1.3, only `Zend\Stratigility\FinalHandler` was still using
this functionality:

- It would query the original request to get the original URI when creating a
  404 response message.
- It passes the decorated request and response instances to `onerror` handlers.

Starting with 1.3.0, we now deprecate these message decorators, and recommend
against their usage.

If you still need access to the original request, response, or URI instance, we
recommend the following:

- Pipe `Zend\Stratigility\Middleware\OriginalMessages` as the outermost layer of
  your application. This will inject the following request attributes into
  layers beneath it:
    - `originalRequest`, mapping to the request provided to it at invocation.
    - `originalResponse`, mapping to the response provided to it at invocation.
    - `originalUri`, mapping to the URI composed by the request provided to it at
      invocation.

You can then access these values within other middleware:

```php
$originalRequest = $request->getAttribute('originalRequest');
$originalResponse = $request->getAttribute('originalResponse');
$originalUri = $request->getAttribute('originalUri');
```

Internally, starting with 1.3.0, we have updated the request decorator to add
the `originalRequest` attribute, and the `FinalHandler` to check for this,
instead of the decorated instance.

Finally, if you are creating an `onerror` handler for the `FinalHandler`, update
your typehints to refer to the PSR-7 request and response interfaces instead of
the Stratigility decorators, if you aren't already.

The `Zend\Stratigility\Http` classes, interfaces, and namespace are removed
in version 2.0.0.

## Error handling

Prior to version 1.3, the recommended way to handle errors was via
[error middleware](../error-handlers.md#legacy-error-middleware), special
middleware that accepts an additional initial argument representing an error. On
top of this, we provide the concept of a "final handler", pseudo-middleware that
is executed by the `Next` implementation when the middleware stack is exhausted,
but no response has been returned.

These approaches, however, have several shortcomings:

- No other middleware frameworks implement the error middleware feature, which
  means any middleware that calls `$next()` with the error argument will not
  work in those other systems, and error middleware written for Stratigility
  cannot be composed in other systems.
- The `FinalHandler` implementation hits edge cases when empty responses are
  intended.
- Neither combination works well with error or exception handlers.

Starting in 1.3, we are promoting using standard middleware layers as error
handlers, instead of using the existing error middleware/final handler system.

The first step is to opt-in to having throwables and exceptions raised by
middleware, instead of having the dispatcher catch them and then invoke
middleware. Do this via the `MiddlewarePipe::raiseThrowables()` method:

```php
$pipeline = new MiddlewarePipe();
$pipeline->raiseThrowables();
```

Once you have done that you may start using some of the new functionality, as
well as augmented existing functionality:

- [NotFoundHandler middleware](../error-handlers.md#handling-404-conditions)
- [ErrorHandler middleware](../error-handlers.md#handling-php-errors-and-exceptions)
- `Zend\Stratigility\NoopFinalHandler` (see next section)

Updating your application to use these features will ensure you are forwards
compatible with version 2 releases.

### No-op final handler

When using the `NotFoundHandler` and `ErrorHandler` middleware (or custom
middleware you drop in place of them), the `FinalHandler` implementation loses
most of its meaning, as you are now handling errors and 404 conditions as
middleware layers.

However, you still need to ensure that the pipeline returns a response,
regardless of how the pipeline is setup, and for that we still need some form of
"final" handler that can do so. (In fact, starting in version 2, the `$out`
argument is renamed to `$delegate`, and is a *required* argument of the
`MiddlewarePipe`.)

Starting in version 1.3, we now offer a `Zend\Stratigility\NoopFinalHandler`
implementation, which simply returns the response passed to it. You can compose
it in your application in one of two ways:

- By passing it explicitly when invoking the middleware pipeline.
- By passing it to `Zend\Diactoros\Server::listen()`.

If you are not using `Zend\Diactoros\Server` to execute your application, but
instead invoking your pipeline manually, use the following:

```php
$response = $app($request, $response, new NoopFinalHandler());
```

If you are using `Zend\Diactoros\Server`, you will need to pass the final
handler you wish to use as an argument to the `listen()` method; that method
will then pass that value as the third argument to `MiddlewarePipe` as shown
above:

```php
$server->listen(new NoopFinalHandler());
```

Both approaches above are fully forwards compatible with version 2, and will
work in all version 1 releases as well.

(You can also compose your own custom final handler; it only needs to accept a
request and a response, and be guaranteed to return a response instance.)

To summarize:

- Call the `raiseThrowables()` method of your `MiddlewarePipe` instance to
  opt-in to the new error handling strategy.
- Use the new `Zend\Stratigility\Middleware\NotFoundHandler` as the innermost
  layer of your application pipeline in order to provide 404 responses.
- Use the new `Zend\Stratigility\Middleware\ErrorHandler` middleware as the
  outermost (or close to outermost) layer of your application pipeline in order
  to handle exceptions.
- Use the `Zend\Stratigility\NoopFinalHandler` as the `$out` argument when
  dispatching your application pipeline.

## http-interop compatibility

### http-middleware 0.2.0 and Stratigility 1.3

Starting in version 1.3.0, we offer compatibility with
[http-interop/http-middleware 0.2.0](https://github.com/http-interop/http-middleware/tree/ff545c87e97bf4d88f0cb7eb3e89f99aaa53d7a9).
That version of the specification defines the following interfaces:

```php
namespace Interop\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface DelegateInterface
{
    public function process(RequestInterface $request) : ResponseInterface;
}

interface MiddlewareInterface
{
    public function process(RequestInterface $request, DelegateInterface $delegate) : ResponseInterface;
}

interface ServerMiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate) : ResponseInterface;
}
```

The support in version 1.3.0 consists of the following:

- `MiddlewarePipe` now also implements `ServerMiddlewareInterface`, and allows
  piping either type of http-interop middleware.
- `Next` now also implements `DelegateInterface`.
- `Dispatch` is now capable of dispatching either http-interop middleware type,
  in addition to legacy callable middleware.

Additionally, `MiddlewarePipe` will now allow composing a *response prototype*;
this is a PSR-7 `ResponseInterface` instance. If not set, the first time the
pipeline is invoked via its `__invoke()` method, it will set the prototype from
the provided `$response` argument. When present, any callable, non-error
middleware piped to the pipeline will be wrapped in a
`Zend\Stratigility\Middleware\CallableMiddlewareWrapper` instance, which
converts it into an http-interop middleware type; when processed, the response
prototype will be passed to the callable for the response argument.

### http-middleware 0.4.1 and Stratigility 2.0

http-interop/http-middleware 0.4.1 introduces breaking changes in the
interfaces, including the following:

- The namespace changes from `Interop\Http\Middleware` to
  `Interop\Http\ServerMiddleware`, signaling a change indicating that the project
  now only targets server-side middleware.

- The interface `ServerMiddlewareInterface` is now more simply
  `MiddlewareInterface`, as the namespace indicates its usage in server-side
  applications. `Interop\Http\Middleware\MiddlewareInterface`, which targeted
  client-side middleware, was removed entirely.

- The method `DelegateInterface::process` now accepts specifically a
  `Psr\Http\Message\ServerRequestInterface`, and not the more general
  `RequestInterface`.

The first two changes required only a change in import statements to
accommodate, and could have been imported in parallel to the 0.2.0 interfaces.
However, the second represents a signature change, which has necessitated a
major version bump in Stratigility in order to remain compatible.

Stratigility 2.0.0 therefor targets http-interop/http-middleware 0.4.1, and that
version (and compatible versions) only.

Additionally, starting in version 2.0.0, `MiddlewarePipe` *will no longer implement
`Zend\Stratigility\MiddlewareInterface`, and only implement the
http-interop/http-middleware `MiddlewareInterface`*. This has several
repercussions.

### Callable middleware in version 1.3.0

Callable middleware can be used without change in version 1.3.0. However, we
recommend updating your code to prepare for version 2.0.0.

First, **we recommend *never* using the `$response` argument provided to
middleware.**

The reason for this recommendation is two-fold. First, the http-interop/http-middleware
interfaces do not provide it, and, as such, using it within your middleware
makes your middleware incompatible. Second, and more importantly, is due to the
reason why http-interop does not include the argument: usage can lead to
inconsistent and/or unexpected results.

As an example, consider the following:

```php
use Zend\Diactoros\Response\JsonResponse;

$pipeline->pipe(function ($request, $response, $next) {
    return $next($request, $response->withHeader('X-Foo', 'Bar'));
});

$pipeline->pipe(function ($request, $response, $next) {
    return new JsonResponse(['ack' => time()]);
});
```

The first, outer layer of middleware sets a response header. However, the
second, inner middleware, *creates and returns an entirely new response*,
making the new header disappear.

As such, we recommend rewriting such middleware to modify the *returned*
response instead:

```php
use Zend\Diactoros\Response\JsonResponse;

$pipeline->pipe(function ($request, $response, $next) {
    $response = $next($request, $response);
    return $response->withHeader('X-Foo', 'Bar');
});

$pipeline->pipe(function ($request, $response, $next) {
    return new JsonResponse(['ack' => time()]);
});
```

The above will have the expected result for whatever middleware is nested
beneath it, as it will operate on the returned response, and have consistent
results.

Second, either wrap your middleware in `CallableMiddlewareWrapper`, or ensure
your pipeline composes a *response prototype* (doing so will implicitly
decorate callable middleware). Either of these will ensure your middleware will
work with http-interop/http-middleware delegators.

```php
use Zend\Stratigility\Middleware\CallableMiddlewareWrapper;

// Manually decorating callable middleware for use with http-middleware:
$pipeline->pipe(new CallableMiddlewareWrapper($middleware, $response));

// Auto-decorate middleware by providing a response prototype:
$pipeline->setResponsePrototype($response);
$pipeline->pipe($middleware);
```

> ### CallableMiddlewareWrapper and Stratigility 2.0
>
> As noted above, version 2 of Stratigility is incompatible with version 1.3 due
> to signature changes in the http-middleware project. However, if you wrap your
> callable middleware using `CallableMiddlewareWrapper`, you will need to make
> no changes in your application to make it forwards compatible.
>
> We recommend using this strategy if you need to do a stepped transition to
> Stratigility 2.0.

Third, and optionally, you can make one or both of the following changes to
your callable middleware:

- Typehint the final `$next` argument against `Interop\Http\Middleware\DelegateInterface`;
  optionally, rename it to `$delegate`. This will require a slight change to
  how you invoke the next layer as well; see below.    
- Remove the `$response` argument from your signature; if you do, make sure you
  typehint the delegate argument, and make it required.

As an example of the first:

```php
function ($request, $response, DelegateInterface $delegate) {
    $response = $delegate->process($request);
    return $response->withHeader('X-Foo', 'Bar');
}
```

As an example of adopting both practices:

```php
function ($request, DelegateInterface $delegate) {
    $response = $delegate->process($request);
    return $response->withHeader('X-Foo', 'Bar');
}
```

At this point, you have essentially implemented `Interop\Http\Middleware\ServerMiddlewareInterface`
(with the notable exception of not type-hinting the `$request` argument).
When you pipe such callable middleware to `MiddlewarePipeline`, it will be
wrapped in a `Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper`,
which simply proxies to the middleware when processed.

> ### DelegateInterface and Stratigility 2.0
>
> Since the namespace within http-interop/http-middleware changes between
> version 0.2.0 and 0.4.1, the above strategy will require making changes
> multiple times: once when upgrading to Stratigility 1.3, and another when
> upgrading to 2.0. As such, we recommend instead decorating your callable
> middleware using the `CallableMiddlewareWrapper`, until such time as you can
> rewrite your middleware to implement the http-middleware 0.4.1 interfaces.

Finally, if you are so inclined, you can rewrite your middleware to
specifically implement one or the other of the http-interop/http-middleware
interfaces. This is particularly relevant for class-based middleware, but can
also be accomplished by using PHP 7 anonymous classes.

As an example, consider the following middleware class:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

class PingMiddleware
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        return new JsonResponse(['ack' => time()]);
    }
}
```

This could be rewritten as follows:

```php
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

class PingMiddleware implements ServerMiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return new JsonResponse(['ack' => time()]);
    }
}
```

If we were dealing with callable middleware instead:

```php
use Zend\Diactoros\Response\JsonResponse;

$pipeline->pipe(function ($request, $response, $next) {
    return new JsonResponse(['ack' => time()]);
});
```

we could wrap this in an anonymous class instead:

```php
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

$pipeline->pipe(new class implements ServerMiddlewareInterface {
    public function (ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return new JsonResponse(['ack' => time()]);
    }
});
```

> Using anonymous classes is likely overkill, as both v1.3.0 and v2.0.0 support
> piping closures.

If you want your middleware to work with either http-interop/http-middleware or
with the pre-1.3.0 middleware signature, you can do that as well. To accomplish
this, we provide `Zend\Stratigility\Delegate\CallableDelegateDecorator`, which
will wrap a `callable $next` such that it may be used as a `DelegateInterface`
implementation:

```php
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;

class PingMiddleware implements ServerMiddlewareInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        return $this->process($request, new CallableDelegateDecorator($next, $response));
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return new JsonResponse(['ack' => time()]);
    }
}
```

> ### Implementing http-interop between Stratigility 1.3 and 2.0
>
> While you _can_ write your middleware to implement the
> http-interop/http-middleware middleware interface, please be aware that if you
> do so, you will need to take additional steps when upgrading from 1.3 to 2.0.
> 
> In most cases, you should be able to simply change the import statements
> within your class file. As an example, if you have the following in your
> Stratigility 1.3-based project:
> 
> ```php
> use Interop\Http\Middleware\DelegateInterface;
> use Interop\Http\Middleware\ServerMiddlewareInterface;
> ```
>
> The imports would become:
>
> ```php
> use Interop\Http\ServerMiddleware\DelegateInterface;
> use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
> ```

To summarize:

- Never work with the provided `$response` argument, but instead manipulate the
  response returned from calling `$next`.

- Ensure your pipeline can decorate callable middleware as http-interop/http-middleware.
  Do this by injecting a response prototype in the pipeline prior to piping any
  middleware. (*Note: this is not necessary if all callable middleware defines
  exactly two parameters, with the second type-hinting on the http-interop
  `DelegateInterface`*.)

- Consider adapting your callable middleware to follow the http-interop middleware
  signature (`function (ServerRequestInterface $request, DelegateInterface $delegate)`);
  this will make it forward-compatible. (Be aware that this may require changes
  in import statements between Stratigility 1.3 and 2.0.)

- Consider updating your class-based middleware to implement the
  http-interop/http-middleware server middleware interface, potentially keeping
  the `__invoke()` method for interoperability with existing callable-based
  middleware runners. (Be aware that this may require changes in import
  statements between Stratigility
  1.3 and 2.0.)

The first and last suggestions in this list are strongly recommended to ensure
forwards compatibility with http-middleware, and to ensure your middleware works
properly across middleware stacks.

### Callable middleware in version 2.0.0

Callable middleware may still be used; however, in order to pipe it to the
pipeline, you must do one of the following:

- Inject a `Zend\Stratigility\Middleware\CallableMiddlewareWrapperFactory`
  instance via the pipeline's `setCallableMiddlewareDecorator()` method,
  prior to piping callable middleware to the instance. This factory class
  requires a `ResponseInterface` in its constructor, and will use that
  response when creating `CallableMiddlewareWrapper` instances.

    ```php
    $factory = new CallableMiddlewareWrapperFactory(new Response());
    $pipeline->setCallableMiddlewareDecorator($factory);
    ```

- Pass a response prototype before piping the callable middleware. If no
  `CallableMiddlewareWrapperFactory` is present, this prototype will be
  used to seed one for use with decorating callable middleware.

    ```php
    $pipeline->setResponsePrototype(new Response());
    ```

- Manually decorate your middleware prior to passing it to the pipeline:

    ```php
    $pipeline->pipe(new CallableMiddlewareWrapper($middleware, $response));
    // or CallableInteropMiddlewareWrapper, if your middleware implements
    // the http-middleware signature already.
    ```

### Invoking MiddlewarePipe instances in version 2.0.0

Invocation of the outermost middleware can now be done in two ways:

- Using `__invoke()`. This now requires a third argument, `$delegate`, which
  may be one of a `callable` accepting `ServerRequestInterface` and `ResponseInterface`
  arguments, or a `DelegateInterface` instance (the former will be decorated
  as the latter, binding the response instance). This will be invoked only
  if the `MiddlewarePipe`'s internal queue is exhausted without returning
  a response, and **must** return a response itself. A good candidate for this
  is the `NoopFinalHandler`.
- Using `process()`. This argument requires a request and `DelegateInterface`
  instance; again, the `DelegateInterface` instance will only be invoked if
  the pipeline's internal queue is exhausted without returning a response.

As examples:

```php
use Zend\Stratigility\NoopFinalHandler;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;

// Using __invoke():
$response = $pipeline($request, $response, new NoopFinalHandler());

// Using process():
$response = $pipeline->process($request, new CallableDelegateDecorator(
    new NoopFinalHandler(),
    $response
));
```

Once you have done so, you can process the returned request via an
[emitter](https://docs.zendframework.com/zend-diactoros/emitting-responses/).

## Deprecated functionality

The following classes, methods, and arguments are deprecated starting in version
1.3.0, and will be removed in version 2.0.0.

- `Zend\Stratigility\FinalHandler` (class)
- `Zend\Stratigility\Dispatch` (class); this class is marked internal already,
  but anybody extending `Next` and/or this class should be aware of its removal.
- `Zend\Stratigility\ErrorMiddlewareInterface` (interface); error middleware
  should now be implemented per the [error handling section above](#error-handling).
- The `$response` argument to `Zend\Stratigility\Next`'s `__invoke()` method.
  The argument, and all following it, are ignored starting in 2.0.0; it is used
  in 1.3.0 to ensure backwards compatibility with existing middleware. The
  `CallableMiddlewareWrapper` also ensures that a response argument is populated
  and present when invoking callable middleware.
- The `$err` argument to `Zend\Stratigility\Next`'s `__invoke()` method.
  Starting in 1.3.0, if a non-null value is encountered, this method will now
  emit an `E_USER_DEPRECATED` notice, referencing this documentation.
- `Zend\Stratigility\Http\Request` (class)
- `Zend\Stratigility\Http\ResponseInterface` (interface)
- `Zend\Stratigility\Http\Response` (class)

## Interface/signature changes

The following signature changes were made with the 2.0.0 release:

- `Zend\Stratigility\Next`:
  - The `$done` constructor argument was renamed to `$nextDelegate`, and now
    allows either `callable` or `Interop\Http\ServerMiddleware\DelegateInterface`
    arguments.
  - The `$response` argument to `__invoke()` was removed.
  - The (optional) `$err` argument to `__invoke()` was removed.

## Removed functionality

The following classes, methods, and arguments are removed starting in version
2.0.0.

- `Zend\Stratigility\Dispatch` (class)
- `Zend\Stratigility\ErrorMiddlewareInterface` (class)
- `Zend\Stratigility\FinalHandler` (class)
- `Zend\Stratigility\MiddlewareInterface`. Define your middleware as callables,
  or using http-interop/http-middleware interfaces instead.
- `Zend\Stratigility\Utils::getArity()` (static method); no longer used
  internally.
- The `$err` argument to `Zend\Stratigility\Next`'s `__invoke()` method. If
  passed, it will now be ignored.
- `Zend\Stratigility\Http\Request` (class)
- `Zend\Stratigility\Http\ResponseInterface` (interface)
- `Zend\Stratigility\Http\Response` (class)
- The `$response` argument to middleware is deprecated; please see the
  [section on callable middleware](callable-middleware-in-version-1.3.0)
  for details, and adapt your middleware to no longer use the argument.
  While the legacy callable signature will continue to work, we recommend
  implementing an http-interop/http-middleware interface.
