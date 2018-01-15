<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

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
 */
function doublePassMiddleware(callable $middleware, ResponseInterface $response = null) : Middleware\DoublePassMiddlewareDecorator
{
    return new Middleware\DoublePassMiddlewareDecorator($middleware, $response);
}
