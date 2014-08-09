<?php
namespace Phly\Conduit;

use Exception;
use Phly\Conduit\Http\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

/**
 * Dispatch middleware
 *
 * This class is an implementation detail of Next.
 *
 * @internal
 */
class Dispatch
{
    /**
     * Dispatch middleware
     *
     * Given a route (which contains the handler for given middleware),
     * the $err value passed to $next, $next, and the request and response
     * objects, dispatch a middleware handler.
     *
     * If $err is non-falsy, and the current handler has an arity of 4,
     * it will be dispatched.
     *
     * If $err is falsy, and the current handler has an arity of < 4,
     * it will be dispatched.
     *
     * In all other cases, the handler will be ignored, and $next will be
     * invoked with the current $err value.
     *
     * If an exception is raised when executing the handler, the exception
     * will be assigned as the value of $err, and $next will be invoked
     * with it.
     *
     * @param Route $route
     * @param mixed $err
     * @param Request $request
     * @param Response $response
     * @param callable $next
     */
    public function __invoke(
        Route $route,
        $err,
        Request $request,
        Response $response,
        callable $next
    ) {
        $arity    = Utils::getArity($route->handler);
        $hasError = (bool) $err;
        $handler  = $route->handler;

        // @todo Trigger event with Route, original URL from request?

        try {
            if ($hasError && $arity === 4) {
                call_user_func($handler, $err, $reqest, $response, $next);
                return;
            } 
            
            if (! $hasError && $arity < 4) {
                call_user_func($handler, $request, $response, $next);
                return;
            }
        } catch (Exception $e) {
            $err = $e;
        }

        $next($err);
    }
}
