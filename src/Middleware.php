<?php
namespace Phly\Conduit;

use ArrayObject;
use InvalidArgumentException;
use Phly\Http\Uri;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Middleware
 *
 * Middleware accepts a request and a response, and optionally a
 * callback "$next" (called if the middleware wants to allow further
 * middleware to process the incoming request).
 *
 * The request and response objects are decorated using the Phly\Conduit\Http
 * variants in this package, ensuring that the request may store arbitrary
 * properties, and the response exposes the convenience write(), end(), and
 * isComplete() methods.
 *
 * Middleware can also accept an initial argument, an error; if middleware
 * accepts errors, it will only be called when an either an exception
 * is raised, or $next is called with an argument (the argument is considered
 * the error condition).
 *
 * Middleware that does not need or desire further processing should not
 * call $next, and should usually call $response->end().
 *
 * Inspired by Sencha Connect.
 *
 * @see https://github.com/sencha/connect
 */
class Middleware
{
    /**
     * @var ArrayObject
     */
    protected $stack;

    /**
     * Constructor
     *
     * Initializes the stack.
     */
    public function __construct()
    {
        $this->stack = new ArrayObject(array());
    }

    /**
     * Handle a request
     *
     * Takes the stack, creates a Next handler, and delegates to the
     * Next handler.
     *
     * If $out is a callable, it is used as the "final handler" when
     * $next has exhausted the stack; otherwise, a FinalHandler instance
     * is created and passed to $next during initialization.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $out
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        $request  = $this->decorateRequest($request);
        $response = $this->decorateResponse($response);

        $done   = is_callable($out) ? $out : new FinalHandler();
        $next   = new Next($this->stack, $request, $response, $done);
        $result = $next();

        if ($result instanceof Response) {
            return $result;
        }
        return $response;
    }

    /**
     * Attach middleware to the conduit
     *
     * Was "use", but "use" is a reserved keyword in PHP
     *
     * Each middleware can be associated with a particular path; if that
     * path is matched when that middleware is invoked, it will be processed;
     * otherwise it is skipped.
     *
     * No path means it should be executed every request cycle.
     *
     * A handler can be any callable, or an object with a handle() method.
     *
     * Handlers with arity >= 4 are considered error handlers, and will
     * be executed when a handler calls $next with an argument or raises
     * an exception.
     *
     * @param string|callable|object $path Either a URI path prefix, or a handler
     * @param null|callable|object $handler A handler
     * @return self
     */
    public function pipe($path, $handler = null)
    {
        if (! is_string($path)) {
            $handler = $path;
            $path    = '/';
        }

        $path = $this->normalizePipePath($path);

        // Ensure we have a valid handler
        if (! is_callable($handler)
            && (! is_object($handler) || ! method_exists($handler, 'handle'))
        ) {
            throw new InvalidArgumentException(
                'Handler must be callable or an object implementing the method "handle"'
            );
        }

        // Munge Object::handle() to a callback
        if (! is_callable($handler)
            && (is_object($handler) && method_exists($handler, 'handle'))
        ) {
            $handler = $this->createPipeHandlerCallback($handler);
        }

        // @todo Trigger event here with route details?
        $this->stack->append(new Route($path, $handler));
        return $this;
    }

    /**
     * Normalize a path used when defining a pipe
     *
     * Strips trailing slashes, and prepends a slash.
     *
     * @param string $path
     * @return string
     */
    private function normalizePipePath($path)
    {
        // Strip trailing slash
        if ('/' === substr($path, -1)) {
            $path = substr($path, 0, -1);
        }

        // Prepend slash if missing
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Create a callback for an object implementing a handle method
     *
     * Uses the method arity to create a closure wrapping the call.
     *
     * @param object $handler
     * @return callable
     */
    private function createPipeHandlerCallback($handler)
    {
        if (Utils::getArity($handler) < 4) {
            // Regular handler
            return function ($request, $response, $next) use ($handler) {
                $handler->handle($request, $response, $next);
            };
        }

        // Error handler
        return function ($err, $request, $response, $next) use ($handler) {
            $handler->handle($err, $request, $response, $next);
        };
    }

    /**
     * Decorate the Request instance
     *
     * @param Request $request
     * @return Http\Request
     */
    private function decorateRequest(Request $request)
    {
        if ($request instanceof Http\Request) {
            return $request;
        }

        return new Http\Request($request);
    }

    /**
     * Decorate the Response instance
     *
     * @param Response $response
     * @return Http\Response
     */
    private function decorateResponse(Response $response)
    {
        if ($response instanceof Http\Response) {
            return $response;
        }

        return new Http\Response($response);
    }
}
