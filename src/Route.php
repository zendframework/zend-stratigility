<?php
namespace Phly\Conduit;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * Value object representing route-based middleware
 *
 * Details the subpath on which the middleware is active, and the
 * handler for the middleware itself.
 */
class Route
{
    /**
     * @var callable
     */
    protected $handler;

    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $path
     * @param callable $handler
     */
    public function __construct($path, callable $handler)
    {
        if (! is_string($path)) {
            throw new InvalidArgumentException('Path must be a string');
        }

        $this->path    = $path;
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
