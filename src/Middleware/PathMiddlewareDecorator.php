<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as RequestHandlerInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use Zend\Stratigility\Exception;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

class PathMiddlewareDecorator implements MiddlewareInterface
{
    /** @var MiddlewareInterface */
    private $middleware;

    /** @var string Path prefix under which the middleware is segregated.  */
    private $prefix;

    /**
     * @param string $prefix
     */
    public function __construct($prefix, MiddlewareInterface $middleware)
    {
        if (! is_string($prefix)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '$prefix argument to %s must be a string; received %s',
                __CLASS__,
                is_object($prefix) ? get_class($prefix) : gettype($prefix)
            ));
        }
        $this->prefix = $this->normalizePrefix($prefix);
        $this->middleware = $middleware;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        $path = $request->getUri()->getPath();
        $path = $path ?: '/';

        // Current path is shorter than decorator path
        if (strlen($path) < strlen($this->prefix)) {
            return $handler->{HANDLER_METHOD}($request);
        }

        // Current path does not match decorator path
        if (substr(strtolower($path), 0, strlen($this->prefix)) !== strtolower($this->prefix)) {
            return $handler->{HANDLER_METHOD}($request);
        }

        // Skip if match is not at a border ('/', '.', or end)
        $border = $this->getBorder($path);
        if ($border && '/' !== $border && '.' !== $border) {
            return $handler->{HANDLER_METHOD}($request);
        }

        // Trim off the part of the url that matches the prefix if it is non-empty
        $requestToProcess = (! empty($this->prefix) && $this->prefix !== '/')
            ? $this->prepareRequestWithTruncatedPrefix($request)
            : $request;

        // Process our middleware.
        // If the middleware calls on the handler, the handler should be provided
        // the original request, as this indicates we've left the path-segregated
        // layer.
        return $this->middleware->process(
            $requestToProcess,
            new PathRequestHandlerDecorator($handler, $request)
        );
    }

    /**
     * @param string $path
     * @return string
     */
    private function getBorder($path)
    {
        if ($this->prefix === '/') {
            return '/';
        }

        $length = strlen($this->prefix);
        return strlen($path) > $length ? $path[$length] : '';
    }

    /**
     * @return ServerRequestInterface
     */
    private function prepareRequestWithTruncatedPrefix(ServerRequestInterface $request)
    {
        $uri  = $request->getUri();
        $path = $this->getTruncatedPath($this->prefix, $uri->getPath());
        $new  = $uri->withPath($path);
        return $request->withUri($new);
    }

    /**
     * @param string $segment
     * @param string $path
     * @return string
     * @throws Exception\PathOutOfSyncException if path prefix is longer than
     *     the path
     */
    private function getTruncatedPath($segment, $path)
    {
        if ($segment === $path) {
            // Decorated path and current path are the same; return empty string
            return '';
        }

        $length = strlen($segment);
        if (strlen($path) > $length) {
            // Strip decorated path from start of current path
            return substr($path, $length);
        }

        if ('/' === substr($segment, -1)) {
            // Re-try by submitting with / stripped from end of segment
            return $this->getTruncatedPath(rtrim($segment, '/'), $path);
        }

        // Segment is longer than path; this is a problem.
        throw Exception\PathOutOfSyncException::forPath($this->prefix, $path);
    }

    /**
     * Ensures that the right-most slash is trimmed for prefixes of more than
     * one character, and that the prefix begins with a slash.
     *
     * @param string $prefix
     * @return string
     */
    private function normalizePrefix($prefix)
    {
        $prefix = strlen($prefix) > 1 ? rtrim($prefix, '/') : $prefix;
        if ('/' !== substr($prefix, 0, 1)) {
            $prefix = '/' . $prefix;
        }
        return $prefix;
    }
}
