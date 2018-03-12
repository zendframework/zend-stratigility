# Composing middleware

The easiest way to compose middleware is to write closures or middleware classes
and attach them to a `Zend\Stratigility\MiddlewarePipe` instance. You can nest
`MiddlewarePipe` instances to create groups of related middleware, and attach
them using a base path so they only execute if that path is matched.

```php
$api = new MiddlewarePipe();  // API middleware collection
$api->pipe(/* ... */);        // repeat as necessary

$app = new MiddlewarePipe();  // Middleware representing the application
$app->pipe('/api', $api);     // API middleware attached to the path "/api"
```

> ### Use PathMiddlewareDecorator
>
> - Since 2.2.0
>
> Starting in 2.2.0, the two argument form of `pipe()` is deprecated in favor of
> piping a `Zend\Stratigility\Middleware\PathMiddlewareDecorator` instance
> instead. The above example would then become:
>
> ```php
> $app->pipe(new PathMiddlewareDecorator('/api', $api));
> ```
>
> This form is forwards compatible with the version 3 release.

> ### Request path changes when path matched
>
> When you pipe middleware using a path (other than '' or '/') or use the
> `PathMiddlewareDecorator`, the middleware is dispatched with a request that
> strips the matched segment(s) from the start of the path before dispatching
> the middleware. Using the previous example, if the path `/api/users/foo` is
> matched, the `$api` middleware will receive a request with the path
> `/users/foo`. This allows middleware segregated by path to be re-used without
> changes to its own internal routing.

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

## Extending the MiddlewarePipe

Another approach is to extend the `Zend\Stratigility\MiddlewarePipe` class
itself, particularly if you want to allow attaching other middleware to your
own middleware. In such a case, you will generally override the `process()`
method to perform any additional logic you have, and then call on the parent in
order to iterate through your stack of middleware:

```php
class CustomMiddleware extends MiddlewarePipe
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // perform some work...

        // delegate to parent
        parent::process($request, $delegate);

        // maybe do more work?
    }
}
```

Another approach using this method would be to override the constructor to add
in specific middleware, perhaps using configuration provided. In this case,
make sure to also call `parent::__construct()` to ensure the middleware queue
is initialized; we recommend doing this as the first action of the method.

```php
use Zend\Stratigility\MiddlewarePipe;

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

These approaches are particularly suited for cases where you may want to
implement a specific workflow for an application segment using existing
middleware, but do not necessarily want that middleware applied to all requests
in the application.

> ### Extension is deprecated
>
> Starting in version 2.2.0, extension of the `MiddlewarePipe` class is
> deprecated, as version 3.0.0 will mark the class `final.
>
> Instead of extension, we thus recommend composition.
>
> When doing so, you will implement the http-interop `MiddlewareInterface` (and,
> in v3, the PSR-15 `MiddlewareInterface`), and compose the `MiddlewarePipe`
> instance internally, delegating to it.
>
> As a complete example, combining the previous two examples:
>
> ```php
> class CustomMiddleware imlements MiddlewareInterface
> {
>     private $pipeline;
> 
>     public function __construct($configuration)
>     {
>         // Do something with configuration ...
> 
>         // Create the pipeline:
>         $this->pipeline = new MiddlewarePipe();
> 
>         // Attach some middleware ...
>         $this->pipeline->pipe(/* some middleware */);
>     }
> 
>     public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
>     {
>         // perform some work...
> 
>         // delegate to parent
>         $this->pipeline->process($request, $handler);
> 
>         // maybe do more work?
>     }
> }
> ```
