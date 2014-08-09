<?php
namespace Phly\Conduit;

use OutOfRangeException;

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
