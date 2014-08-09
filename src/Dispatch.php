<?php
namespace Phly\Conduit;

use Exception;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Dispatch
{
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
            } elseif (! $hasError && $arity < 4) {
                call_user_func($handler, $request, $response, $next);
            }
        } catch (Exception $e) {
            $err = $e;
        }

        $next($err);
    }
}
