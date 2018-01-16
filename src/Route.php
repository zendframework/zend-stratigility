<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use InvalidArgumentException;
use OutOfRangeException;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface as ServerMiddlewareInterface;

/**
 * Value object representing route-based middleware
 *
 * Details the subpath on which the middleware is active, and the
 * handler for the middleware itself.
 *
 * @internal
 * @deprecated since 2.2.0, to be removed in 3.0.0.
 * @property-read callable $handler Handler for this route
 * @property-read string $path Path for this route
 */
class Route
{
    /**
     * @var ServerMiddlewareInterface
     */
    protected $handler;

    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $path
     * @param ServerMiddlewareInterface $handler
     */
    public function __construct($path, ServerMiddlewareInterface $handler)
    {
        if (! is_string($path)) {
            throw new InvalidArgumentException('Path must be a string');
        }

        $this->path    = $path;

        if (! in_array($this->path, ['', '/'])
            && ! $handler instanceof Middleware\PathMiddlewareDecorator
        ) {
            trigger_error(sprintf(
                'Providing a path to a %s instance is deprecated; please use the'
                . ' %s to decorate your path-segregated middleware instead.',
                __CLASS__,
                Middleware\PathMiddlewareDecorator::class
            ), E_USER_DEPRECATED);
            $handler = new Middleware\PathMiddlewareDecorator($path, $handler);
        }

        $this->handler = $handler;
    }

    /**
     * @param mixed $name
     * @return mixed
     * @throws OutOfRangeException for invalid properties
     */
    public function __get($name)
    {
        if (! property_exists($this, $name)) {
            throw new OutOfRangeException('Only the path and handler may be accessed from a Route instance');
        }
        return $this->{$name};
    }
}
