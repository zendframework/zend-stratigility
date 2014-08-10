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

    /**
     * Create a URI string based on the parts provided.
     *
     * Parts SHOULD contain the following:
     *
     * - scheme (defaults to http)
     * - host
     * - port (defaults to null)
     * - path
     * - query
     * - fragment
     *
     * All but scheme and host are optional.
     *
     * @param array $parts
     * @return string
     */
    public static function createUriString(array $parts)
    {
        $scheme   = isset($parts['scheme'])   ? $parts['scheme']   : 'http';
        $host     = isset($parts['host'])     ? $parts['host']     : '';
        $port     = isset($parts['port'])     ? $parts['port']     : null;
        $path     = isset($parts['path'])     ? $parts['path']     : '';
        $query    = isset($parts['query'])    ? $parts['query']    : '';
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';

        $uri = sprintf('%s://%s', $scheme, $host);
        if (($host && $port)
            && (($scheme === 'https' && $port && $port !== 443)
                || ($scheme === 'http' && $port && $port !== 80))
        ) {
            $uri .= sprintf(':%d', $port);
        }

        if ($path) {
            if ('/' !== $path[0]) {
                $path = '/' . $path;
            }
            $uri .= $path;
        }

        if ($query) {
            $uri .= sprintf('?%s', $query);
        }

        if ($fragment) {
            $uri .= sprintf('#%s', $fragment);
        }

        return $uri;
    }
}
