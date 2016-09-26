# Error Handlers

In your application, you may need to handle error conditions:

- Errors raised by PHP itself (e.g., inability to open a file or database
  connection).
- Exceptions/throwables raised by PHP and/or code you write or consume.
- Inability of any middleware to handle a request.

You can typically handle these conditions via middleware itself.

## Handling 404 conditions

If no middleware is able to handle the incoming request, this is typically
representative of an HTTP 404 status. Stratigility provides a barebones
middleware that you may register in an innermost layer that will return a 404
condition, `Zend\Stratigility\Middleware\NotFoundHandler`. The class requires a
response prototype instance that it will use to provide the 404 status and a
message indicating the request method and URI used:

```php
// setup layers
$app->pipe(/* ... */);
$app->pipe(/* ... */);
$app->pipe(new NotFoundHandler(new Response());

// execute application
```

Note that it is the last middleware piped into the application! Since it returns
a response, no deeper neseted layers will execute once it has been invoked.

If you would like a templated response, you will need to write your own
middleware; such middleware might look like the following:

```php
class NotFoundMiddleware
{
    private $renderer;

    public function __construct(
        TemplateRendererInterface $renderer,
        ResponseInterface $response
    ) {
        $this->renderer = $renderer;
        $this->response = $response;
    }

    public function __invoke($request)
    {
        $response = $this->response->withStatus(404);
        $response->getBody()->write(
            $this->renderer->render('error::404')
        );
        return $response;
    }
}
```

## Handling PHP errors and exceptions

`Zend\Stratigility\Middleware\ErrorHandler` is a middleware implementation to
register as the *outermost layer* of your application (or one of the outermost
layers). It does the following:

- Creates a PHP error handler that catches any errors in the `error_handling()`
  mask and throws them as `ErrorException` instances.
- Wraps the call to `$next()` in a try/catch block:
  - if no exception is caught, and the result is a response, it returns it.
  - if no exception is caught, it raises an exception, which will be caught.
  - any caught exception is transformed into an error response.

The error response will have a 5XX series status code, and the message will be
derived from the reason phrase, if any is present. You may pass a boolean flag
to the constructor indicating the application is in development mode; if so, the
response will have the stack trace included in the body.

In order to work, it needs a prototype response instance, and, optionally, a
flag indicating the status of development mode (default is production mode):

```php
// setup error handling
$app->pipe(new ErrorHandler(new Response(), $isDevelopmentMode);

// setup layers
$app->pipe(/* ... */);
$app->pipe(/* ... */);
```

As a full example, you can combine the two middleware into the same application
as separate layers:

```php
// setup error handling
$app->pipe(new ErrorHandler(new Response(), $isDevelopmentMode);

// setup layers
$app->pipe(/* ... */);
$app->pipe(/* ... */);

// setup 404 handling
$app->pipe(new NotFoundHandler(new Response());

// execute application
```

The `ErrorHandler` provides no templating facilities, and only responds as text
and/or HTML. If you want to provide a templated response, or a different
serialization and/or markup format, you will need to write your own
implementation. As an example:

```php
use ErrorException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zend\Stratigility\Exception\MissingResponseException;

class TemplatedErrorHandler
{
    private $renderer;
    private $responsePrototype;

    public function __construct(TemplateRendererInterface $renderer, ResponseInterface $responsePrototype)
    {
        $this->renderer = $renderer;
        $this->responsePrototype = $responsePrototype;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        set_error_handler( function ($errno, $errstr, $errfile, $errline) {
            if (! (error_reporting() & $errno)) {
                // error_reporting does not include this error
                return;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            $response = $next($request, $response);

            if (! $response instanceof ResponseInterface) {
                throw new MissingResponseException('Application did not return a response');
            }
        } catch (Throwable $e) {
            $response = $this->handleThrowable($e, $request);
        } catch (\Exception $e) {
            $response = $this->handleThrowable($e, $request);
        }

        restore_error_handler();

        return $response;
    }

    private function handleThrowable($e, ServerRequestInterface $request)
    {
        $response = $this->responsePrototype->withStatus(500);
        $response->write($this->renderer->render('error::error');
        return $response;
    }
}
```

### ErrorHandler Listeners

`Zend\Stratigility\ErrorHandler` provides the ability to attach *listeners*;
these are triggered when an error or exception is caught, and provided with the
exception/throwable raised, the original request, and the final response. These
instances are considered immutable, so listeners are for purposes of
logging/monitoring only.

Attach listeners using `ErrorHandler::attachListener()`:

```php
$errorHandler->attachListener(function ($throwable, $request, $response) use ($logger) {
    $message = sprintf(
        '[%s] %s %s: %s',
        date('Y-m-d H:i:s'),
        $request->getMethod(),
        (string) $request->getUri(),
        $throwable->getMessage()
    );
    $logger->error($message);
});
```

## Legacy error middleware

- Deprecated starting in 1.3.0, to be removed in 2.0.0. Please see the
  [migration guide](migration/to-v2.md#error-handling) for more details, as well
  as the preceding section.

To handle errors, you can write middleware that accepts **exactly** four arguments:

```php
function ($error, $request, $response, $next) { }
```

Alternately, you can implement `Zend\Stratigility\ErrorMiddlewareInterface`.

When using `MiddlewarePipe`, as the queue is executed, if `$next()` is called with an argument, or
if an exception is thrown, middleware will iterate through the queue until the first such error
handler is found. That error handler can either complete the request, or itself call `$next()`.
**Error handlers that call `$next()` SHOULD call it with the error it received itself, or with
another error.**

Error handlers are usually attached at the end of middleware, to prevent attempts at executing
non-error-handling middleware, and to ensure they can intercept errors from any other handlers.
