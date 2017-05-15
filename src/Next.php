<?php
/**
 * @see       https://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Interop\Http\ServerMiddleware\DelegateInterface;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SplQueue;
use Throwable;

/**
 * Iterate a queue of middlewares and execute them.
 */
class Next implements DelegateInterface
{
    /**
     * @var null|DelegateInterface
     */
    private $nextDelegate;

    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * Flag indicating whether or not the dispatcher should raise throwables
     * when encountered, and whether or not $err arguments should raise them;
     * defaults false.
     *
     * @var bool
     */
    private $raiseThrowables = false;

    /**
     * @var string
     */
    private $removed = '';

    /**
     * Constructor.
     *
     * Clones the queue provided to allow re-use.
     *
     * @param SplQueue $queue
     * @param null|DelegateInterface $nextDelegate Next delegate to invoke when the
     *     queue is exhausted.
     * @throws InvalidArgumentException for a non-callable, non-delegate $done
     *     argument.
     */
    public function __construct(SplQueue $queue, DelegateInterface $nextDelegate = null)
    {
        $this->queue        = clone $queue;
        $this->nextDelegate = $nextDelegate;
    }

    /**
     * Invokable form; proxy to process().
     *
     * Ignores any arguments other than the request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception\MissingResponseException If the queue is exhausted, and
     *     no "next delegate" is present.
     * @throws Exception\MissingResponseException If the middleware executed does
     *     not return a response.
     */
    public function __invoke(ServerRequestInterface $request)
    {
        return $this->process($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception\MissingResponseException If the queue is exhausted, and
     *     no "next delegate" is present.
     * @throws Exception\MissingResponseException If the middleware executed does
     *     not return a response.
     */
    public function process(ServerRequestInterface $request)
    {
        $request  = $this->resetPath($request);

        // No middleware remains; done
        if ($this->queue->isEmpty()) {
            if ($this->nextDelegate) {
                return $this->nextDelegate->process($request);
            }

            throw new Exception\MissingResponseException(sprintf(
                'Queue provided to %s was exhausted, with no response returned',
                get_class($this)
            ));
        }

        $layer           = $this->queue->dequeue();
        $path            = $request->getUri()->getPath() ?: '/';
        $route           = $layer->path;
        $normalizedRoute = (strlen($route) > 1) ? rtrim($route, '/') : $route;

        // Skip if layer path does not match current url
        if (substr(strtolower($path), 0, strlen($normalizedRoute)) !== strtolower($normalizedRoute)) {
            return $this->process($request);
        }

        // Skip if match is not at a border ('/', '.', or end)
        $border = $this->getBorder($path, $normalizedRoute);
        if ($border && '/' !== $border && '.' !== $border) {
            return $this->process($request);
        }

        // Trim off the part of the url that matches the layer route
        if (! empty($route) && $route !== '/') {
            $request = $this->stripRouteFromPath($request, $route);
        }

        $middleware = $layer->handler;
        $response = $middleware->process($request, $this);

        if (! $response instanceof ResponseInterface) {
            throw new Exception\MissingResponseException(sprintf(
                "Last middleware executed did not return a response.\nMethod: %s\nPath: %s\n.Handler: %s",
                $request->getMethod(),
                $request->getUri()->getPath(),
                get_class($middleware)
            ));
        }

        return $response;
    }

    /**
     * Toggle the "raise throwables" flag on.
     *
     * @deprecated Since 2.0.0; this functionality is now a no-op.
     * @return void
     */
    public function raiseThrowables()
    {
    }

    /**
     * Reset the path, if a segment was previously stripped
     *
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    private function resetPath(ServerRequestInterface $request)
    {
        if (! $this->removed) {
            return $request;
        }

        $uri  = $request->getUri();
        $path = $uri->getPath();

        if (strlen($path) >= strlen($this->removed)
            && 0 === strpos($path, $this->removed)
        ) {
            $path = str_replace($this->removed, '', $path);
        }

        $resetPath = $this->removed . $path;

        // Strip trailing slash if current path does not contain it and
        // original path did not have it
        if ('/' === $path && '/' !== substr($this->removed, -1)) {
            $resetPath = rtrim($resetPath, '/');
        }

        // Normalize to remove double-slashes
        $resetPath = str_replace('//', '/', $resetPath);

        $new  = $uri->withPath($resetPath);
        $this->removed = '';
        return $request->withUri($new);
    }

    /**
     * Determine the border between the request path and current route
     *
     * @param string $path
     * @param string $route
     * @return string
     */
    private function getBorder($path, $route)
    {
        if ($route === '/') {
            return '/';
        }
        $routeLength = strlen($route);
        return (strlen($path) > $routeLength) ? $path[$routeLength] : '';
    }

    /**
     * Strip the route from the request path
     *
     * @param ServerRequestInterface $request
     * @param string $route
     * @return ServerRequestInterface
     */
    private function stripRouteFromPath(ServerRequestInterface $request, $route)
    {
        $this->removed = $route;

        $uri  = $request->getUri();
        $path = $this->getTruncatedPath($route, $uri->getPath());
        $new  = $uri->withPath($path);

        // Root path of route is treated differently
        if ($path === '/' && '/' === substr($uri->getPath(), -1)) {
            $this->removed .= '/';
        }

        return $request->withUri($new);
    }

    /**
     * Strip the segment from the start of the given path.
     *
     * @param string $segment
     * @param string $path
     * @return string Truncated path
     * @throws RuntimeException if the segment does not begin the path.
     */
    private function getTruncatedPath($segment, $path)
    {
        if ($path === $segment) {
            // Segment and path are same; return empty string
            return '';
        }

        $segmentLength = strlen($segment);
        if (strlen($path) > $segmentLength) {
            // Strip segment from start of path
            return substr($path, $segmentLength);
        }

        if ('/' === substr($segment, -1)) {
            // Re-try by submitting with / stripped from end of segment
            return $this->getTruncatedPath(rtrim($segment, '/'), $path);
        }

        // Segment is longer than path. There's an issue
        throw new RuntimeException(
            'Layer and request path have gone out of sync'
        );
    }
}
