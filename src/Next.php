<?php
namespace Phly\Conduit;

use ArrayObject;
use Phly\Http\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
     * @param null|ServerRequestInterface|ResponseInterface|mixed $state
     * @param null|ResponseInterface $response
     * @return ResponseInterface
     */
    public function __invoke($state = null, ResponseInterface $response = null)
    {
        $err          = null;
        $resetRequest = false;

        if ($state instanceof ResponseInterface) {
            $this->response = $state;
        }

        if ($state instanceof ServerRequestInterface) {
            $this->request = $state;
            $resetRequest  = true;
        }

        if ($response instanceof ResponseInterface) {
            $this->response = $response;
        }

        if (! $state instanceof ServerRequestInterface
            && ! $state instanceof ResponseInterface
        ) {
            $err = $state;
        }

        $dispatch = $this->dispatch;
        $done     = $this->done;
        $this->resetPath($this->request, $resetRequest);

        // No middleware remains; done
        if (! isset($this->stack[$this->index])) {
            return $done($err);
        }

        $layer = $this->stack[$this->index++];
        $path  = parse_url($this->request->getAbsoluteUri(), PHP_URL_PATH) ?: '/';
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

        $result = $dispatch($layer, $err, $this->request, $this->response, $this);
        if ($result instanceof ResponseInterface) {
            $this->response = $result;
        }
        return $this->response;
    }

    /**
     * Reset the path, if a segment was previously stripped
     *
     * @param Http\Request $request
     * @param bool $resetRequest Whether or not the request was reset in this iteration
     */
    private function resetPath(Http\Request $request, $resetRequest = false)
    {
        if (! $this->removed) {
            return;
        }

        $uri  = new Uri($request->getAbsoluteUri());
        $path = $uri->path;

        if ($resetRequest
            && strlen($path) >= strlen($this->removed)
            && 0 === strpos($path, $this->removed)
        ) {
            $path = str_replace($this->removed, '', $path);
        }

        $path = $this->removed . $path;
        $new  = $uri->setPath($path);
        $this->removed = '';
        $this->request = $request->setAbsoluteUri((string) $new);
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

        $uri  = new Uri($this->request->getAbsoluteUri());
        $path = substr($uri->path, strlen($route));
        $new  = $uri->setPath($path);
        $this->request = $this->request->setAbsoluteUri((string) $new);
    }
}
