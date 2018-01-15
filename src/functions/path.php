<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

/**
 * Convenience function for creating path-segregated middleware.
 *
 * Usage:
 *
 * <code>
 * use function Zend\Stratigility\path;
 *
 * $pipeline->pipe(path('/foo', $middleware));
 * </code>
 *
 * @param string $path Path prefix to match in order to dispatch $middleware
 * @return Middleware\PathMiddlewareDecorator
 */
function path($path, MiddlewareInterface $middleware)
{
    return new Middleware\PathMiddlewareDecorator($path, $middleware);
}
