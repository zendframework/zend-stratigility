<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Stratigility;

use Interop\Http\Server\MiddlewareInterface;

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
 */
function path(string $path, MiddlewareInterface $middleware) : Middleware\PathMiddlewareDecorator
{
    return new Middleware\PathMiddlewareDecorator($path, $middleware);
}
