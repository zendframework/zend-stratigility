<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Psr\Http\Message\ResponseInterface;

/**
 * Convenience wrapper around instantiation of a DoublePassMiddlewareDecorator instance.
 *
 * Usage:
 *
 * <code>
 * $pipeline->pipe(doublePassMiddleware(function ($req, $res, $next) {
 *     // do some work
 * }));
 * </code>
 *
 * Optionally, pass a response prototype as well, if using a PSR-7
 * implementation other than zend-diactoros:
 *
 * <code>
 * $pipeline->pipe(doublePassMiddleware(function ($req, $res, $next) {
 *     // do some work
 * }, $responsePrototype));
 * </code>
 *
 * @return Middleware\DoublePassMiddlewareDecorator
 */
function doublePassMiddleware(
    callable $middleware,
    ResponseInterface $response = null
) {
    return new Middleware\DoublePassMiddlewareDecorator($middleware, $response);
}
