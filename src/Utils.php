<?php
namespace Phly\Conduit;

use ReflectionFunction;
use ReflectionMethod;

/**
 * Utility methods
 */
abstract class Utils
{
    /**
     * Get the arity of a handler
     *
     * @param string|callable|object $callable
     * @return int
     */
    public static function getArity($callable)
    {
        if (is_object($callable)
            && method_exists($callable, '__invoke')
        ) {
            $r = new ReflectionMethod($callable, '__invoke');
            return $r->getNumberOfRequiredParameters();
        }

        if (is_object($callable)
            && method_exists($callable, 'handle')
        ) {
            $r = new ReflectionMethod($callable, 'handle');
            return $r->getNumberOfRequiredParameters();
        }

        if (! is_callable($callable)) {
            return 0;
        }

        $r = new ReflectionFunction($callable);
        return $r->getNumberOfRequiredParameters();
    }
}
