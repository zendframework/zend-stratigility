<?php
namespace Phly\Conduit;

use Phly\Http\Uri;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SplQueue;

/**
 * Iterate a queue of middlewares and execute them.
 */
class Next
{
    /**
     * @var Dispatch
     */
    private $dispatch;

    /**
     * @var Callable
     */
    private $done;

    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * @var string
     */
    private $removed = '';

    /**
     * @param SplQueue $queue
     * @param callable $done
     */
    public function __construct(SplQueue $queue, callable $done)
    {
        $this->queue    = $queue;
        $this->done     = $done;

        $this->dispatch = new Dispatch();
    }

    /**
     * Call the next Route in the queue.
     *
     * Next requires that a request and response are provided; these will be
     * passed to any middleware invoked, including the $done callable, if
     * invoked.
     *
     * If the $err value is not null, the invocation is considered to be an
     * error invocation, and Next will search for the next error middleware
     * to dispatch, passing it $err along with the request and response.
     *
     * Once dispatch is complete, if the result is a response instance, that
     * value will be returned; otherwise, the currently registered response
     * instance will be returned.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param null|mixed $err
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $err = null
    ) {
        $dispatch = $this->dispatch;
        $done     = $this->done;
        $request  = $this->resetPath($request);

        // No middleware remains; done
        if ($this->queue->isEmpty()) {
            return $done($err, $request, $response);
        }

        $layer           = $this->queue->dequeue();
        $path            = $request->getUri()->getPath() ?: '/';
        $route           = $layer->path;
        $normalizedRoute = (strlen($route) > 1) ? rtrim($route, '/') : $route;

        // Skip if layer path does not match current url
        if (substr(strtolower($path), 0, strlen($normalizedRoute)) !== strtolower($normalizedRoute)) {
            return $this($request, $response, $err);
        }

        // Skip if match is not at a border ('/', '.', or end)
        $border = $this->getBorder($path, $normalizedRoute);
        if ($border && '/' !== $border && '.' !== $border) {
            return $this($request, $response, $err);
        }

        // Trim off the part of the url that matches the layer route
        if (! empty($route) && $route !== '/') {
            $request = $this->stripRouteFromPath($request, $route);
        }

        $result = $dispatch($layer, $err, $request, $response, $this);

        return ($result instanceof ResponseInterface ? $result : $response);
    }

    /**
     * Reset the path, if a segment was previously stripped
     *
     * @param Http\Request $request
     * @return Http\Request
     */
    private function resetPath(Http\Request $request)
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
        if ('/' !== $path && '/' !== substr($this->removed, -1)) {
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
        $border = (strlen($path) > strlen($route))
            ? $path[strlen($route)]
            : '';
        $border = ($route === '/') ? '/' : $border;
        return $border;
    }

    /**
     * Strip the route from the request path
     *
     * @param Http\Request $request
     * @param string $route
     * @return Http\Request
     */
    private function stripRouteFromPath(Http\Request $request, $route)
    {
        $this->removed = $route;

        $uri  = $request->getUri();
        $path = $this->getTruncatedPath($route, $uri->getPath());
        $new  = $uri->withPath($path);

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
