<?php
namespace Phly\Conduit;

use ArrayObject;

/**
 * Iterate a stack of middlewares and execute them
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
     * @var int
     */
    private $index = 0;

    /**
     * @var string
     */
    private $removed = '';

    /**
     * @var Http\Request
     */
    private $request;

    /**
     * @var Http\Response
     */
    private $response;

    /**
     * @var ArrayObject
     */
    private $stack;

    /**
     * @param ArrayObject $stack
     * @param Http\Request $request
     * @param Http\Response $response
     * @param callable $done
     */
    public function __construct(ArrayObject $stack, Http\Request $request, Http\Response $response, callable $done)
    {
        $this->dispatch = new Dispatch();

        $this->stack    = $stack;
        $this->request  = $request;
        $this->response = $response;
        $this->done     = $done;
    }

    /**
     * Call the next Route in the stack
     *
     * @param null|mixed $err
     */
    public function __invoke($err = null)
    {
        $request  = $this->request;
        $dispatch = $this->dispatch;
        $done     = $this->done;

        $this->resetPath($request);

        // No middleware remains; done
        if (! isset($this->stack[$this->index])) {
            return $done($err);
        }

        $layer = $this->stack[$this->index++];
        $path  = $this->request->getUrl()->path ?: '/';
        $route = $layer->path;

        // Skip if layer path does not match current url
        if (substr(strtolower($path), 0, strlen($route)) !== strtolower($route)) {
            return $this($err);
        }

        // Skip if match is not at a border ('/', '.', or end)
        $border = $this->getBorder($path, $route);
        if ($border && '/' !== $border && '.' !== $border) {
            return $this($err);
        }

        // Trim off the part of the url that matches the layer route
        if (strlen($route) !== 0 && $route !== '/') {
            $this->stripRouteFromPath($route);
        }

        $dispatch($layer, $err, $this->request, $this->response, $this);
    }

    /**
     * Reset the path, if a segment was previously stripped
     *
     * @param Http\Request $request
     */
    private function resetPath(Http\Request $request)
    {
        if (! $this->removed) {
            return;
        }

        $uri  = $this->request->getUrl();
        $path = $this->removed . $uri->path;
        $request->setUrl($uri->setPath($path));
        $this->removed = '';
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
     * @param string $route
     */
    private function stripRouteFromPath($route)
    {
        $this->removed = $route;

        $uri  = $this->request->getUrl();
        $path = substr($uri->path, strlen($route));
        $this->request->setUrl($uri->setPath($path));
    }
}
