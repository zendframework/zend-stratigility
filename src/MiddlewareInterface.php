<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Middleware.
 *
 * Middleware accepts a request and a response, and a
 * callback `$next` (called if the middleware wants to allow the *next*
 * middleware to process the incoming request, or to delegate output to another
 * process).
 *
 * Middleware that does not need or desire further processing should not
 * call `$next`, and should instead return a response.
 *
 * For the purposes of Stratigility, `$next` is typically one of either an instance
 * of `Next` or an instance of `NoopFinalHandler`, and, as such, should follow
 * those calling semantics.
 *
 * @deprecated since 2.0.0; to be removed in 3.0.0.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming request and/or response.
     *
     * Accepts a server-side request and a response instance, and does
     * something with them.
     *
     * If the response is not complete and/or further processing would not
     * interfere with the work done in the middleware, or if the middleware
     * wants to delegate to another process, it can use the `$next` callable
     * if present:
     *
     * <code>
     * return $next($request, $response);
     * </code>
     *
     * Middleware MUST return a response, or the result of $next (which should
     * return a response).
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next);
}
