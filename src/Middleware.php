<?php
namespace Phly\Conduit;

use ArrayObject;
use InvalidArgumentException;
use Phly\Conduit\Http\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

/**
 * Middleware
 *
 * Middleware accepts a request and a response, and optionally a
 * callback "$next" (called if the middleware wants to allow further
 * middleware to process the incoming request).
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
    private $stack;

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
     */
    public function pipe($path, $handler = null)
    {
        if (! is_string($path)) {
            $handler = $path;
            $path    = '/';
        }

        // Strip trailing slash
        if ('/' === substr($path, -1)) {
            $path = substr($path, 0, -1);
        }

        // Prepend slash if missing
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Ensure we have a valid handler
        if (! is_callable($handler)
            && (! is_object($handler) || ! method_exists($handler, 'handle'))
        ) {
            throw new InvalidArgumentException('Handler must be callable or an object implementing the method "handle"');
        }

        // Munge Object::handle() to a callback
        if (! is_callable($handler)
            && (is_object($handler) && method_exists($handler, 'handle'))
        ) {
            if (Utils::getArity($handler) < 4) {
                $handler = function ($request, $response, $next) use ($handler) {
                    $handler->handle($request, $response, $next);
                };
            } else {
                $handler = function ($err, $request, $response, $next) use ($handler) {
                    $handler->handle($err, $request, $response, $next);
                };
            }
        }

        // @todo Trigger event here with route details?
        $this->stack[] = new Route($path, $handler);
        return $this;
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
     * @return void
     */
    public function handle(Request $request, Response $response, callable $out = null)
    {
        $stack = $this->stack;
        $url   = $request->setUrl($this->getUrlFromRequest($request));
        $done  = is_callable($out) ? $out : new FinalHandler($request, $response);
        $next  = new Next($this->stack, $request, $response, $done);
        $next();
    }

    /**
     * Ensure the request URI is an Http\Uri instance
     *
     * @param Request $request
     * @return Http\Uri
     */
    private function getUrlFromRequest(Request $request)
    {
        $url = $request->getUrl();
        if (! $url instanceof Http\Uri) {
            $url = new Http\Uri($url);
        }
        return $url;
    }
}
