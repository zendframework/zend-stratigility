<?php
namespace Phly\Conduit;

use ArrayObject;
use Phly\Conduit\Http\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

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
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var bool
     */
    private $slashAdded = false;

    /**
     * @var ArrayObject
     */
    private $stack;

    /**
     * @param ArrayObject $stack
     * @param Request $request
     * @param Response $response
     * @param callable $done
     */
    public function __construct(ArrayObject $stack, Request $request, Response $response, callable $done)
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
        $dispatch = $this->dispatch;
        $done     = $this->done;

        if ($this->slashAdded) {
            $uri  = $this->request->getUrl();
            $path = substr($uri->path, 1);
            $this->setUriPath($this->request, $path);
            $this->slashAdded = false;
        }

        if ($this->removed) {
            $uri  = $this->request->getUrl();
            $path = $this->removed . $uri->path;
            $this->setUriPath($this->request, $path);
            $this->removed = '';
        }

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
        $border = (strlen($path) > strlen($route))
            ? $path[strlen($route)]
            : '';
        $border = ($route === '/') ? '/' : $border;
        if ($border && '/' !== $border && '.' !== $border) {
            return $this($err);
        }

        // Trim off the part of the url that matches the layer route
        if (strlen($route) !== 0 && $route !== '/') {
            $this->removed = $route;

            $uri  = $this->request->getUrl();
            $path = substr($uri->path, strlen($route));
            $this->setUriPath($this->request, $path);

            if ($path[0] !== '/') {
                $path = '/' . $path;
                $this->setUriPath($this->request, $path);
                $this->slashAdded = true;
            }
        }

        $dispatch($layer, $err, $this->request, $this->response, $this);
    }

    /**
     * Set the request uri with a new path
     *
     * Since Uri objects are immutable, this takes the previous Http\Uri instance,
     * and the new path, and creates a new Http\Uri instance with the new path,
     * setting it into the request object.
     *
     * @param Request $request
     * @param string $path
     */
    private function setUriPath(Request $request, $path)
    {
        $path = $path ?: '/';
        $uri  = $request->getUrl();

        $request->setUrl(new Http\Uri(Utils::createUriString(array(
            'scheme'   => $uri->scheme,
            'host'     => $uri->host,
            'port'     => $uri->port,
            'path'     => $path,
            'query'    => $uri->query,
            'fragment' => $uri->fragment,
        ))));
    }
}
