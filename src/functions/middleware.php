<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Psr\Http\Message\ResponseInterface;

/**
 * Convenience wrapper around instantiation of a CallableMiddlewareDecorator instance.
 *
 * Usage:
 *
 * <code>
 * $pipeline->pipe(middleware(function ($req, $handler) {
 *     // do some work
 * }));
 * </code>
 *
 * @return Middleware\CallableMiddlewareDecorator
 */
function middleware(callable $middleware)
{
    return new Middleware\CallableMiddlewareDecorator($middleware);
}
