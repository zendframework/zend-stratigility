<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Stratigility;

/**
 * Convenience wrapper around instantiation of a CallableMiddlewareDecorator instance.
 *
 * Usage:
 *
 * <code>
 * use function Zend\Stratigility\middleware;
 *
 * $pipeline->pipe(middleware(function ($req, $handler) {
 *     // do some work
 * }));
 * </code>
 */
function middleware(callable $middleware) : Middleware\CallableMiddlewareDecorator
{
    return new Middleware\CallableMiddlewareDecorator($middleware);
}
